<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ArchiveSection;
use App\Models\AmongusAnalysisDraft;
use App\Models\Member;
use App\Models\Onedayarchive;
use App\Models\Role;
use App\Services\Archive\Contracts\PlatformArchiveFetcher;
use App\Services\Archive\StreamArchiveCollector;
use App\Services\Archive\ParticipantComputationService;
use App\Services\Archive\SectionTimeWindows;
use App\Services\Archive\TwitchArchiveFetcher;
use App\Services\Archive\YoutubeArchiveFetcher;
use App\Services\GameGenreInferenceService;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 前夜の配信（マラソン配信は翌6時頃まで続くことがある）が確実に終わっている
// 昼過ぎの時間帯に、直近3日分だけ差分同期する。
Schedule::command('youtube:sync-member-archives --days=3')
    ->dailyAt('12:00')
    ->withoutOverlapping();

// TwitchのVOD（約60日で失効）やOPENREC（最新20件のみ）を消える前に記録するため、
// タグの有無に関わらず全メンバーの直近の配信アーカイブを毎日stream_archivesに貯める。
Schedule::command('archives:collect-streams --days=3')
    ->dailyAt('12:30')
    ->withoutOverlapping();

Artisan::command('youtube:sync-member-archives
    {--member= : Member ID or exact member name}
    {--from= : Start date in YYYY-MM-DD}
    {--to= : End date in YYYY-MM-DD}
    {--days=14 : Number of recent days to sync when --from is omitted}
    {--all : Sync all available uploads}
    {--overwrite : Replace existing member archive URLs}
    {--dry-run : Show what would be updated without saving}', function (YoutubeMemberArchiveSyncService $syncService) {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $from = null;
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'), $timezone)
            : Carbon::now($timezone);

        if ($this->option('from')) {
            $from = Carbon::parse($this->option('from'), $timezone);
        } elseif (!$this->option('all')) {
            $from = Carbon::now($timezone)->subDays((int) $this->option('days'));
        }

        $members = Member::query()
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('youtube_channel_id')->where('youtube_channel_id', '<>', '');
                })->orWhere(function ($query) {
                    $query->whereNotNull('arujan_youtube_channel_id')->where('arujan_youtube_channel_id', '<>', '');
                });
            })
            ->when($this->option('member'), function ($query, string $member) {
                if (ctype_digit($member)) {
                    $query->whereKey((int) $member);
                } else {
                    $query->where('name', $member);
                }
            })
            ->orderBy('id')
            ->get()
            // メインメンバーを先に処理し、その回のメインが2人確定してから
            // サブメンバー以下の登録判定ができるようにする。
            ->sortByDesc(fn (Member $member) => Member::isMainMemberName($member->name))
            ->values();

        if ($members->isEmpty()) {
            $this->warn('同期対象のメンバーがいません。members.youtube_channel_id を登録してください。');

            return 0;
        }

        $this->info(sprintf(
            'Syncing %d member(s), from=%s, to=%s, overwrite=%s, dry-run=%s',
            $members->count(),
            $from?->toDateString() ?? 'all',
            $to->toDateString(),
            $this->option('overwrite') ? 'yes' : 'no',
            $this->option('dry-run') ? 'yes' : 'no'
        ));

        foreach ($members as $member) {
            try {
                $result = $syncService->syncMember(
                    $member,
                    $from,
                    $to,
                    (bool) $this->option('overwrite'),
                    (bool) $this->option('dry-run')
                );

                $this->line(sprintf(
                    '%s: fetched=%d updated=%d skipped=%d',
                    $result['member'],
                    $result['fetched'],
                    $result['updated'],
                    $result['skipped']
                ));

                foreach ($result['multiple_candidate_dates'] ?? [] as $date => $count) {
                    $this->line("  {$date}: {$count} live archive videos found (Shorts/edited videos excluded). Matched per section by time window.");
                }

                foreach ($result['needs_review'] ?? [] as $review) {
                    $this->warn(sprintf(
                        '  [要確認] %s: section_id=%d (%s) — %s',
                        $review['date'],
                        $review['section_id'],
                        $review['section_type'] ?? '(unknown)',
                        $review['reason']
                    ));
                }
            } catch (Throwable $exception) {
                $this->error("{$member->name}: {$exception->getMessage()}");
            }
        }

        return 0;
    })->purpose('Sync member YouTube archive URLs into calendar detail rows');

Artisan::command('youtube:resolve-member-channel-ids
    {--member= : Member ID or exact member name}
    {--overwrite : Replace existing YouTube channel IDs}
    {--dry-run : Show what would be updated without saving}', function (YoutubeMemberArchiveSyncService $syncService) {
        $members = Member::query()
            ->whereNotNull('youtube_url')
            ->where('youtube_url', '<>', '')
            ->when(!$this->option('overwrite'), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('youtube_channel_id')
                        ->orWhere('youtube_channel_id', '');
                });
            })
            ->when($this->option('member'), function ($query, string $member) {
                if (ctype_digit($member)) {
                    $query->whereKey((int) $member);
                } else {
                    $query->where('name', $member);
                }
            })
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            $this->warn('チャンネルIDを回収できるメンバーがいません。members.youtube_url を登録してください。');

            return 0;
        }

        foreach ($members as $member) {
            try {
                $channelId = $syncService->resolveChannelId($member->youtube_url);

                if (!$channelId) {
                    $this->warn("{$member->name}: チャンネルIDを見つけられませんでした。");
                    continue;
                }

                if (!$this->option('dry-run')) {
                    $member->update(['youtube_channel_id' => $channelId]);
                }

                $this->line("{$member->name}: {$channelId}");
            } catch (Throwable $exception) {
                $this->error("{$member->name}: {$exception->getMessage()}");
            }
        }

        return 0;
    })->purpose('Resolve member YouTube channel IDs from registered YouTube URLs');

Artisan::command('youtube:search-member-channels
    {--member= : Member ID or exact member name}
    {--all : Search candidates for all members}
    {--limit=3 : Number of candidates per member}
    {--apply : Save the first candidate as youtube_url and youtube_channel_id}
    {--overwrite : Replace existing YouTube URL/channel ID}', function (YoutubeMemberArchiveSyncService $syncService) {
        if (!$this->option('member') && !$this->option('all')) {
            $this->warn('--member=メンバー名 か --all を指定してください。誤登録防止のため、デフォルトでは全員検索しません。');

            return 0;
        }

        $members = Member::query()
            ->when(!$this->option('overwrite'), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('youtube_channel_id')
                        ->orWhere('youtube_channel_id', '');
                });
            })
            ->when($this->option('member'), function ($query, string $member) {
                if (ctype_digit($member)) {
                    $query->whereKey((int) $member);
                } else {
                    $query->where('name', $member);
                }
            })
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            $this->warn('検索対象のメンバーがいません。');

            return 0;
        }

        foreach ($members as $member) {
            try {
                $candidates = $syncService->searchChannelCandidates($member->name, (int) $this->option('limit'));
                $first = $candidates[0] ?? null;

                $this->line('');
                $this->info($member->name);

                if (!$first) {
                    $this->warn('  候補なし');
                    continue;
                }

                foreach ($candidates as $index => $candidate) {
                    $this->line(sprintf(
                        '  %d. %s / %s / %s',
                        $index + 1,
                        $candidate['title'],
                        $candidate['channel_id'],
                        $candidate['url']
                    ));
                }

                if ($this->option('apply')) {
                    $member->update([
                        'youtube_url' => $first['url'],
                        'youtube_channel_id' => $first['channel_id'],
                    ]);

                    $this->comment("  saved: {$first['url']}");
                }
            } catch (Throwable $exception) {
                $this->error("{$member->name}: {$exception->getMessage()}");
            }
        }

        return 0;
    })->purpose('Search YouTube channel candidates for registered members');

Artisan::command('youtube:sync-member-icons
    {--member= : Member ID or exact member name}
    {--overwrite : Replace existing member avatars}
    {--dry-run : Show what would be updated without saving}', function (YoutubeMemberArchiveSyncService $syncService) {
        $members = Member::query()
            ->whereNotNull('youtube_channel_id')
            ->where('youtube_channel_id', '<>', '')
            ->when(!$this->option('overwrite'), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('avatar')
                        ->orWhere('avatar', '');
                });
            })
            ->when($this->option('member'), function ($query, string $member) {
                if (ctype_digit($member)) {
                    $query->whereKey((int) $member);
                } else {
                    $query->where('name', $member);
                }
            })
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            $this->warn('アイコン同期対象のメンバーがいません。');

            return 0;
        }

        foreach ($members as $member) {
            try {
                $profile = $syncService->fetchChannelProfile($member->youtube_channel_id);
                $thumbnailUrl = $profile['thumbnail_url'] ?? null;

                if (!$thumbnailUrl) {
                    $this->warn("{$member->name}: アイコンURLを取得できませんでした。");
                    continue;
                }

                if (!$this->option('dry-run')) {
                    $member->update(['avatar' => $thumbnailUrl]);
                }

                $this->line("{$member->name}: {$thumbnailUrl}");
            } catch (Throwable $exception) {
                $this->error("{$member->name}: {$exception->getMessage()}");
            }
        }

        return 0;
    })->purpose('Sync member avatars from YouTube channel thumbnails');

Artisan::command('youtube:sync-official-edited-videos
    {--channel-url=https://www.youtube.com/@arujandayo : Official YouTube channel URL or channel ID}
    {--from=2022-12-01 : Start published date in YYYY-MM-DD}
    {--to= : End published date in YYYY-MM-DD}
    {--overwrite : Replace existing official edited video fields}
    {--dry-run : Show what would be updated without saving}', function (YoutubeMemberArchiveSyncService $syncService) {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $from = Carbon::parse($this->option('from'), $timezone);
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'), $timezone)
            : Carbon::now($timezone);

        try {
            $channelId = $syncService->resolveChannelId($this->option('channel-url'));
        } catch (Throwable $exception) {
            $this->error("公式チャンネルIDの取得に失敗しました: {$exception->getMessage()}");

            return 1;
        }

        if (!$channelId) {
            $this->error('公式チャンネルIDを取得できませんでした。--channel-url を確認してください。');

            return 1;
        }

        try {
            $videos = $syncService->fetchChannelUploadVideos($channelId, $from, $to)
                ->reject(fn (array $video) => isLiveArchiveVideo($video))
                ->reject(fn (array $video) => isShortsVideo($video))
                ->sortBy('published_at')
                ->values();
        } catch (Throwable $exception) {
            $this->error("公式編集動画の取得に失敗しました: {$exception->getMessage()}");

            return str_contains($exception->getMessage(), 'quotaExceeded') ? 1 : 0;
        }

        $updated = 0;
        $skipped = 0;

        $this->info(sprintf(
            'Scanning official edited videos, channel=%s, from=%s, to=%s, overwrite=%s, dry-run=%s',
            $channelId,
            $from->toDateString(),
            $to->toDateString(),
            $this->option('overwrite') ? 'yes' : 'no',
            $this->option('dry-run') ? 'yes' : 'no'
        ));

        foreach ($videos as $video) {
            $publishedDate = $video['published_at']->copy()->timezone($timezone)->toDateString();
            $archive = Onedayarchive::firstOrNew(['event_date' => $publishedDate]);

            if (
                $archive->exists
                && !$this->option('overwrite')
                && (!empty($archive->official_edited_title) || !empty($archive->official_edited_video_url))
            ) {
                $skipped++;
                continue;
            }

            $title = trim((string) ($video['title'] ?? ''));
            $title = mb_strlen($title) > 255 ? mb_substr($title, 0, 252) . '...' : $title;

            $archive->official_title = $archive->official_title ?? '';
            $archive->official_video_url = $archive->official_video_url ?? '';
            $archive->thumbnail_url = $archive->thumbnail_url ?? '';
            $archive->description = $archive->description ?? '';
            $archive->official_edited_title = $title;
            $archive->official_edited_video_url = $video['url'];

            if (!$this->option('dry-run')) {
                $archive->save();
            }

            $updated++;
            $this->line(sprintf(
                '%s: %s / %s%s',
                $publishedDate,
                $title,
                $video['url'],
                $this->option('dry-run') ? ' [dry-run]' : ''
            ));
        }

        $this->info("updated={$updated} skipped={$skipped}");

        return 0;
    })->purpose('Register official edited videos into archive rows by YouTube published date');

Artisan::command('youtube:sync-hashtag-archives
    {--member= : Member ID or exact member name}
    {--date= : Target date in YYYY-MM-DD}
    {--from= : Start date in YYYY-MM-DD}
    {--to= : End date in YYYY-MM-DD}
    {--days=7 : Number of recent days to scan when no date/from is supplied}
    {--amongus-only : Register only videos that match both Arujan and Among Us keywords}
    {--overwrite : Replace existing member archive URLs}
    {--dry-run : Show what would be registered without saving}', function (YoutubeMemberArchiveSyncService $syncService, GameGenreInferenceService $gameGenreInference) {
        $timezone = config('app.timezone', 'Asia/Tokyo');

        $targetDate = null;

        if ($this->option('date')) {
            $from = Carbon::parse($this->option('date'), $timezone);
            $to = $from->copy()->addDay();
            $targetDate = $from->toDateString();
        } else {
            $to = $this->option('to')
                ? Carbon::parse($this->option('to'), $timezone)
                : Carbon::now($timezone);

            $from = $this->option('from')
                ? Carbon::parse($this->option('from'), $timezone)
                : Carbon::now($timezone)->subDays((int) $this->option('days'));
        }

        $members = Member::query()
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('youtube_channel_id')->where('youtube_channel_id', '<>', '');
                })->orWhere(function ($query) {
                    $query->whereNotNull('arujan_youtube_channel_id')->where('arujan_youtube_channel_id', '<>', '');
                });
            })
            ->when($this->option('member'), function ($query, string $member) {
                if (ctype_digit($member)) {
                    $query->whereKey((int) $member);
                } else {
                    $query->where('name', $member);
                }
            })
            ->orderBy('id')
            ->get()
            // メインメンバーを先に処理し、その回のメインが2人確定してから
            // サブメンバー以下の新規登録判定ができるようにする。
            ->sortByDesc(fn (Member $member) => Member::isMainMemberName($member->name))
            ->values();

        if ($members->isEmpty()) {
            $this->warn('対象メンバーがいません。');

            return 0;
        }

        $hashtags = ['#アルジャン', 'アルジャン', '#あるじゃん', 'あるじゃん'];
        $registered = 0;
        $skipped = 0;

        $this->info(sprintf(
            'Scanning %d member(s), from=%s, to=%s, dry-run=%s',
            $members->count(),
            $from->toDateString(),
            $to->toDateString(),
            $this->option('dry-run') ? 'yes' : 'no'
        ));

        foreach ($members as $member) {
            try {
                $videos = $syncService->fetchChannelVideos($member, $from, $to);
            } catch (Throwable $exception) {
                $this->error("{$member->name}: {$exception->getMessage()}");
                continue;
            }

            foreach ($videos as $video) {
                $hashtagSearchText = mb_strtolower(implode("\n", [
                    $video['title'] ?? '',
                    $video['description'] ?? '',
                ]));

                if (!isLiveArchiveVideo($video)) {
                    continue;
                }

                if (isShortsVideo($video)) {
                    continue;
                }

                $matchedHashtag = collect($hashtags)->first(
                    fn (string $keyword) => str_contains($hashtagSearchText, mb_strtolower($keyword))
                );

                if (!$matchedHashtag) {
                    continue;
                }

                $inferredGame = $matchedHashtag
                    ? $gameGenreInference->inferFromText(
                        $video['title'] ?? '',
                        $video['description'] ?? '',
                        $video['tags'] ?? []
                    )
                    : null;

                if ($this->option('amongus-only') && $inferredGame !== 'Among Us') {
                    continue;
                }

                $searchText = mb_strtolower(implode("\n", [
                    $video['title'] ?? '',
                    $video['description'] ?? '',
                    implode(' ', $video['tags'] ?? []),
                ]));

                [$date, $sectionType, $sectionLabel] = inferArchiveSectionFromVideo($video, $searchText);

                if ($targetDate && $date !== $targetDate) {
                    continue;
                }

                $existingArchive = Onedayarchive::where('event_date', $date)->first();
                $existingSection = $existingArchive?->sections()->where('section_type', $sectionType)->first();

                // ルール: アルジャンのメインメンバーが2人以上確定していないセクションは
                // 「開催されていない」と判断し、サブメンバー以下の新規登録は保留する
                // （手動編集画面・youtube:sync-member-archivesと同じ制約）。
                // メインメンバー自身の登録はこのチェックを素通りさせる。
                if (!Member::isMainMemberName($member->name)) {
                    $confirmedMainCount = $existingSection
                        ? $existingSection->members()
                            ->wherePivotNotNull('video_url')
                            ->get()
                            ->filter(fn (Member $m) => Member::isMainMemberName($m->name))
                            ->count()
                        : 0;

                    if ($confirmedMainCount < 2) {
                        $skipped++;
                        continue;
                    }
                }

                $archive = $existingArchive ?? Onedayarchive::firstOrNew(['event_date' => $date]);
                $archive->official_title = $archive->official_title ?? '';
                $archive->official_video_url = $archive->official_video_url ?? '';
                $archive->thumbnail_url = $archive->thumbnail_url ?? '';
                $archive->description = $archive->description ?? '';

                if (!$this->option('dry-run') && !$archive->exists) {
                    $archive->save();
                }

                $section = $existingSection ?? ($archive->exists
                    ? ArchiveSection::firstOrNew([
                        'onedayarchive_id' => $archive->id,
                        'section_type' => $sectionType,
                    ])
                    : new ArchiveSection([
                        'section_type' => $sectionType,
                    ]));

                if ($inferredGame && (empty($section->game_genre) || $section->game_genre === 'Among Us')) {
                    $section->game_genre = $inferredGame;
                }

                $section->section_title = $section->section_title ?: $sectionLabel;

                if (!$this->option('dry-run') && !$section->exists) {
                    $section->onedayarchive_id = $archive->id;
                    $section->save();
                } elseif (!$this->option('dry-run') && $section->isDirty()) {
                    $section->save();
                }

                if (!$this->option('dry-run') && $section->exists) {
                    ArchiveSection::query()
                        ->where('onedayarchive_id', $archive->id)
                        ->where('id', '<>', $section->id)
                        ->whereHas('members', function ($query) use ($member, $video) {
                            $query->where('members.id', $member->id)
                                ->where('archive_section_member.video_url', $video['url']);
                        })
                        ->get()
                        ->each(fn (ArchiveSection $otherSection) => $otherSection->members()->detach($member->id));
                }

                $currentUrl = $section->exists
                    ? $section->members()
                        ->where('members.id', $member->id)
                        ->first()
                        ?->pivot
                        ?->video_url
                    : null;

                if ($currentUrl && !$this->option('overwrite')) {
                    $skipped++;
                    continue;
                }

                if (!$this->option('dry-run')) {
                    $section->members()->syncWithoutDetaching([
                        $member->id => ['video_url' => $video['url']],
                    ]);
                }

                $registered++;
                $this->line(sprintf(
                    '%s %s: %s / %s / %s%s',
                    $date,
                    $member->name,
                    $matchedHashtag,
                    $sectionLabel . ' / ' . ($inferredGame ?: 'genre blank'),
                    $video['url'],
                    $this->option('dry-run') ? ' [dry-run]' : ''
                ));
            }
        }

        $this->info("registered={$registered} skipped={$skipped}");

        return 0;
    })->purpose('Register member archive URLs from #アルジャン/#あるじゃん YouTube videos');

Artisan::command('archives:backfill-participants-from-description
    {--date= : Target archive date in YYYY-MM-DD}
    {--from= : Start archive date in YYYY-MM-DD}
    {--to= : End archive date in YYYY-MM-DD}
    {--dry-run : Show what would be added without saving}', function (YoutubeMemberArchiveSyncService $syncService) {
        $query = ArchiveSection::query()
            ->with(['onedayarchive', 'members'])
            ->whereHas('members', fn ($query) => $query->whereNotNull('archive_section_member.video_url'));

        if ($this->option('date')) {
            $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', $this->option('date')));
        } else {
            if ($this->option('from')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '>=', $this->option('from')));
            }

            if ($this->option('to')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '<=', $this->option('to')));
            }
        }

        $sections = $query->orderBy('onedayarchive_id')->get();

        if ($sections->isEmpty()) {
            $this->warn('対象のセクションがありません。');

            return 0;
        }

        $allMemberNames = Member::pluck('id', 'name');
        $membersById = Member::all()->keyBy('id');
        $added = 0;
        $skippedNoEvidence = 0;
        $checked = 0;

        foreach ($sections as $section) {
            $videoUrl = $section->members->first(fn (Member $m) => !empty($m->pivot->video_url))?->pivot->video_url;

            if (!$videoUrl) {
                continue;
            }

            $checked++;

            try {
                $video = $syncService->fetchVideosByUrls([$videoUrl])->first();
            } catch (Throwable $exception) {
                $this->error("section={$section->id}: {$exception->getMessage()}");
                continue;
            }

            if (!$video) {
                continue;
            }

            $foundNames = extractParticipantNamesFromDescription(
                $video['description'] ?? '',
                $allMemberNames->keys()->all()
            );

            $existingIds = $section->members->pluck('id')->all();

            foreach ($foundNames as $name) {
                $memberId = $allMemberNames[$name] ?? null;

                if (!$memberId || in_array($memberId, $existingIds, true)) {
                    continue;
                }

                $eventDate = $section->onedayarchive->event_date ?? '?';
                $candidateMember = $membersById->get($memberId);

                // ルール: 他人の動画概要欄に名前があっただけでは登録しない。
                // 本人のチャンネルでこのセクションの時間帯に一致する動画が実際に見つかった場合のみ追加する。
                $matchedVideo = $candidateMember
                    ? $syncService->findSectionVideoForMember($candidateMember, $section)
                    : null;

                if (!$matchedVideo) {
                    $otherUrl = $candidateMember?->other_url;
                    $otherIsYoutube = $otherUrl && str_contains(mb_strtolower($otherUrl), 'youtu');

                    $otherPlatformHints = [];

                    if (!empty($candidateMember?->twitch_url)) {
                        $otherPlatformHints[] = "Twitch: {$candidateMember->twitch_url}";
                    }

                    if (!empty($otherUrl) && !$otherIsYoutube) {
                        $label = $candidateMember->other_platform ?: 'その他媒体';
                        $otherPlatformHints[] = "{$label}: {$otherUrl}";
                    }

                    if ($otherIsYoutube) {
                        // findSectionVideoForMember がサブチャンネルも既に試した上で見つからなかった結果。
                        $hintText = 'サブチャンネル（' . $otherUrl . '）も確認済みですが該当動画が見つかりませんでした。'
                            . ($otherPlatformHints ? '他に' . implode(' / ', $otherPlatformHints) . 'も手動確認してください' : '別媒体での配信がないか手動確認してください');
                    } else {
                        $hintText = $otherPlatformHints
                            ? '登録されている他媒体を手動確認してください（' . implode(' / ', $otherPlatformHints) . '）'
                            : '他媒体の登録もないため、サブチャンネルやツイキャス等含め手動確認してください';
                    }

                    $this->warn("{$eventDate} section={$section->id}: {$name} を概要欄で検出しましたが、本人のYouTubeでは確認できませんでした。{$hintText}");
                    $skippedNoEvidence++;
                    continue;
                }

                $this->line("{$eventDate} section={$section->id}: {$name} を追加（本人の動画で確認済み: {$matchedVideo['url']}）");

                if (!$this->option('dry-run')) {
                    $section->members()->attach($memberId, [
                        'video_url' => $matchedVideo['url'],
                        'needs_review' => false,
                        'review_reason' => null,
                    ]);
                }

                $added++;
            }
        }

        $this->info("checked={$checked} added={$added} skipped_no_evidence={$skippedNoEvidence}");

        return 0;
    })->purpose('Backfill missing archive section participants by parsing video descriptions (requires the participant\'s own confirmed video)');

Artisan::command('archives:auto-extract-participants
    {--date= : Target date in YYYY-MM-DD}
    {--from= : Start date in YYYY-MM-DD}
    {--to= : End date in YYYY-MM-DD}
    {--days=14 : Number of recent days to scan when --date/--from/--all are omitted}
    {--all : Scan from the service start date (2022-12-01) to today}
    {--overwrite-genre : Overwrite already-set game genres}
    {--dry-run : Show what would be added without saving}', function (
        ParticipantComputationService $service,
        YoutubeMemberArchiveSyncService $youtubeService
    ) {
        // 「ゲーム内容と参加メンバーを抽出する」ボタン（ParticipantComputationService::computeForDate）を
        // 日付範囲で自動実行し、結果をそのまま保存するバッチ版。youtube:sync-member-archivesは
        // 「本人がYouTubeへ自分の配信をアップしているか」でしか参加者を追加できないため、
        // 自分の配信をアップしないメンバー（他メンバーの概要欄への記載やジャンル一致でしか
        // 検出できない参加者）が漏れてしまう。このコマンドはそのクロスリファレンス判定込みで
        // 参加者・ジャンルを補完する。本人の動画URLが確認できなかった参加者はneeds_review=trueで
        // 追加し、要確認バッジで後から人手確認できるようにする。
        $timezone = config('app.timezone', 'Asia/Tokyo');

        if ($this->option('date')) {
            $from = Carbon::parse($this->option('date'), $timezone);
            $to = $from->copy();
        } elseif ($this->option('all')) {
            $from = Carbon::parse('2022-12-01', $timezone);
            $to = $this->option('to') ? Carbon::parse($this->option('to'), $timezone) : Carbon::now($timezone);
        } else {
            $to = $this->option('to') ? Carbon::parse($this->option('to'), $timezone) : Carbon::now($timezone);
            $from = $this->option('from')
                ? Carbon::parse($this->option('from'), $timezone)
                : $to->copy()->subDays((int) $this->option('days'));
        }

        $sectionLabels = [
            'pre' => '0次会',
            'primary' => '1次会',
            'secondary' => '2次会',
            'third' => '3次会',
            'fourth' => '4次会',
        ];

        $this->info(sprintf(
            'Auto-extracting participants from %s to %s, overwrite-genre=%s, dry-run=%s',
            $from->toDateString(),
            $to->toDateString(),
            $this->option('overwrite-genre') ? 'yes' : 'no',
            $this->option('dry-run') ? 'yes' : 'no'
        ));

        $sectionsRegistered = 0;
        $membersAdded = 0;
        $membersNeedsReview = 0;
        $skippedNoStream = 0;
        $skippedNotEnoughMainMembers = 0;

        for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
            $dateString = $date->toDateString();
            $existingArchive = Onedayarchive::where('event_date', $dateString)->first();

            if ($existingArchive && $existingArchive->no_stream) {
                $skippedNoStream++;
                continue;
            }

            try {
                $computed = $service->computeForDate($date->copy()->startOfDay());
            } catch (Throwable $exception) {
                $this->error("{$dateString}: {$exception->getMessage()}");
                continue;
            }

            foreach ($computed as $type => $data) {
                if (empty($data['members'])) {
                    continue;
                }

                $existingSection = $existingArchive?->sections->firstWhere('section_type', $type);
                $isNewRegistration = !$existingSection || $existingSection->members()->doesntExist();
                $memberIds = collect($data['members'])->pluck('id');

                if ($isNewRegistration && !Member::hasEnoughMainMembers($memberIds)) {
                    $skippedNotEnoughMainMembers++;
                    continue;
                }

                $this->line(sprintf(
                    '%s %s: %s (%s)',
                    $dateString,
                    $sectionLabels[$type] ?? $type,
                    collect($data['members'])->pluck('name')->implode('/'),
                    $data['genre'] ?: 'ジャンル不明'
                ));

                if ($this->option('dry-run')) {
                    continue;
                }

                $archive = Onedayarchive::firstOrNew(['event_date' => $dateString]);
                $archive->official_title = $archive->official_title ?? '';
                $archive->official_video_url = $archive->official_video_url ?? '';
                $archive->official_edited_title = $archive->official_edited_title ?? '';
                $archive->official_edited_video_url = $archive->official_edited_video_url ?? '';
                $archive->thumbnail_url = $archive->thumbnail_url ?? '';
                $archive->description = $archive->description ?? '';
                $archive->save();

                $section = ArchiveSection::firstOrCreate(
                    ['onedayarchive_id' => $archive->id, 'section_type' => $type],
                    ['section_title' => $sectionLabels[$type] ?? $type, 'game_genre' => '']
                );

                if ($data['genre'] && (empty($section->game_genre) || $this->option('overwrite-genre'))) {
                    $section->update(['game_genre' => $data['genre']]);
                }

                $existingMemberIds = $section->members()->pluck('members.id')->all();

                foreach ($data['members'] as $memberRow) {
                    if (in_array($memberRow['id'], $existingMemberIds, true)) {
                        continue;
                    }

                    $member = Member::find($memberRow['id']);

                    if (!$member) {
                        continue;
                    }

                    $matchedVideo = $youtubeService->findSectionVideoForMember($member, $section);

                    $section->members()->syncWithoutDetaching([
                        $member->id => [
                            'video_url' => $matchedVideo['url'] ?? null,
                            'needs_review' => !$matchedVideo,
                            'review_reason' => $matchedVideo
                                ? null
                                : '自動抽出で参加者候補として検出されましたが、本人の動画URLは未確認です。手動で確認してください。',
                        ],
                    ]);

                    $membersAdded++;

                    if (!$matchedVideo) {
                        $membersNeedsReview++;
                    }
                }

                $sectionsRegistered++;
            }
        }

        $this->info(sprintf(
            'sections=%d members_added=%d needs_review=%d skipped_no_stream=%d skipped_not_enough_main_members=%d',
            $sectionsRegistered,
            $membersAdded,
            $membersNeedsReview,
            $skippedNoStream,
            $skippedNotEnoughMainMembers
        ));

        return 0;
    })->purpose('Auto-run the "extract game content and participants" logic across a date range and persist results, flagging unconfirmed video URLs for review');

Artisan::command('archives:collect-streams
    {--from= : Start date in YYYY-MM-DD (e.g. 2022-12-01 for the initial backfill)}
    {--to= : End date in YYYY-MM-DD (defaults to now)}
    {--days=3 : Number of recent days to collect when --from is omitted}
    {--member= : Member ID or exact member name}', function (StreamArchiveCollector $collector) {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'), $timezone)->endOfDay()
            : Carbon::now($timezone);
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'), $timezone)->startOfDay()
            : Carbon::now($timezone)->subDays((int) $this->option('days'))->startOfDay();

        $members = Member::query()
            ->when($this->option('member'), function ($query, string $member) {
                if (ctype_digit($member)) {
                    $query->whereKey((int) $member);
                } else {
                    $query->where('name', $member);
                }
            })
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            $this->error('対象メンバーが見つかりません。');

            return 1;
        }

        $this->info("{$members->count()}人分の配信アーカイブを {$from->toDateString()} 〜 {$to->toDateString()} で取り込みます。");

        $saved = $collector->import($members, $from, $to, function (string $member, string $platform, string $message) {
            $this->warn("[{$platform}] {$member}: {$message}");
        });

        $this->info("{$saved}件をstream_archivesに保存しました（既存分は上書き）。");

        return 0;
    })->purpose('Collect every member\'s live-stream archives (with or without the Arujan tag) into stream_archives');

Artisan::command('archives:export-streams
    {--from= : Event date from, in YYYY-MM-DD}
    {--to= : Event date to, in YYYY-MM-DD}
    {--output= : Output CSV path (defaults to storage/app/exports/stream-archives-YYYYmmdd-His.csv)}', function (StreamArchiveCollector $collector) {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $output = $this->option('output')
            ?: storage_path('app/exports/stream-archives-' . Carbon::now($timezone)->format('Ymd-His') . '.csv');

        $count = $collector->exportCsv(
            $output,
            $this->option('from') ? Carbon::parse($this->option('from'), $timezone) : null,
            $this->option('to') ? Carbon::parse($this->option('to'), $timezone) : null,
        );

        $this->info("{$count}件を書き出しました: {$output}");

        return 0;
    })->purpose('Export stream_archives to a CSV for sorting in a spreadsheet');

// routes/console.phpはテスト実行時など、同一PHPプロセス内でLaravelアプリケーションが
// 複数回ブートストラップされるたびに再require()される。トップレベル関数宣言は
// require_once ではなく require で読み込まれるため、ガード無しだと2回目以降の
// ブートストラップで「関数を再宣言できません」という致命的エラーになる。
if (!function_exists('extractParticipantNamesFromDescription')) {

function extractParticipantNamesFromDescription(string $description, array $allMemberNames): array
{
    $found = [];
    $lines = preg_split('/\r\n|\r|\n/', $description) ?: [];

    foreach ($lines as $line) {
        foreach (explode('/', $line) as $token) {
            $token = trim($token);

            if ($token !== '' && in_array($token, $allMemberNames, true)) {
                $found[] = $token;
            }
        }
    }

    return array_values(array_unique($found));
}

Artisan::command('archives:prune-invalid-arujan-video-links
    {--date= : Target date in YYYY-MM-DD}
    {--from= : Start date in YYYY-MM-DD}
    {--to= : End date in YYYY-MM-DD}
    {--section-type= : Only target a specific section_type (e.g. pre)}
    {--member= : Only target a specific member (ID or exact name)}
    {--quiet-output : Only print summary counts}
    {--dry-run : Show what would be detached without saving}', function (YoutubeMemberArchiveSyncService $youtubeService) {
        $query = ArchiveSection::query()
            ->with(['onedayarchive', 'members'])
            ->whereHas('members', fn ($query) => $query->whereNotNull('archive_section_member.video_url'));

        if ($this->option('section-type')) {
            $query->where('section_type', $this->option('section-type'));
        }

        if ($this->option('member')) {
            $memberOption = $this->option('member');
            $query->whereHas('members', function ($query) use ($memberOption) {
                if (ctype_digit($memberOption)) {
                    $query->where('members.id', (int) $memberOption);
                } else {
                    $query->where('members.name', $memberOption);
                }
            });
        }

        if ($this->option('date')) {
            $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', $this->option('date')));
        } else {
            if ($this->option('from')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '>=', $this->option('from')));
            }

            if ($this->option('to')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '<=', $this->option('to')));
            }
        }

        $sections = $query->orderBy('onedayarchive_id')->get();
        $detached = 0;
        $deletedSections = 0;

        $memberFilter = $this->option('member');

        foreach ($sections as $section) {
            $youtubeMembers = $section->members
                ->filter(fn (Member $member) => isYoutubeUrl($member->pivot->video_url ?? null))
                ->when($memberFilter, function ($members) use ($memberFilter) {
                    return $members->filter(
                        fn (Member $member) => ctype_digit($memberFilter)
                            ? $member->id === (int) $memberFilter
                            : $member->name === $memberFilter
                    );
                });
            $urls = $youtubeMembers
                ->pluck('pivot.video_url')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($urls)) {
                continue;
            }

            try {
                $videos = $youtubeService->fetchVideosByUrls($urls);
            } catch (Throwable $exception) {
                if (str_contains($exception->getMessage(), 'quotaExceeded')) {
                    $this->error('YouTube API quota exceeded. Stop pruning and retry after quota resets.');

                    return 1;
                }

                $this->error("section {$section->id}: {$exception->getMessage()}");
                continue;
            }

            $invalidUrls = $videos
                ->filter(fn (array $video) => !isLiveArchiveVideo($video) || isShortsVideo($video) || !hasArujanKeywordInTitleOrDescription($video))
                ->pluck('url')
                ->all();

            if (empty($invalidUrls)) {
                continue;
            }

            foreach ($youtubeMembers as $member) {
                if (!in_array($member->pivot->video_url, $invalidUrls, true)) {
                    continue;
                }

                $date = $section->onedayarchive?->event_date ?? '-';

                if (!$this->option('quiet-output')) {
                    $this->line(sprintf(
                        '%s %s %s: detach %s / %s',
                        $date,
                        $section->section_title ?: $section->section_type,
                        $section->game_genre ?: 'genre blank',
                        $member->name,
                        $member->pivot->video_url
                    ));
                }

                if (!$this->option('dry-run')) {
                    $section->members()->detach($member->id);
                }

                $detached++;
            }

            if (!$this->option('dry-run')) {
                $section->load('members');

                if ($section->members->isEmpty()) {
                    $section->delete();
                    $deletedSections++;
                }
            }
        }

        $this->info("detached={$detached} deleted_sections={$deletedSections}");

        return 0;
    })->purpose('Detach YouTube archive URLs that only matched tags or shorts, not #アルジャン title/description');

Artisan::command('archives:prune-unevidenced-pre-sections
    {--date= : Target date in YYYY-MM-DD}
    {--from= : Start date in YYYY-MM-DD}
    {--to= : End date in YYYY-MM-DD}
    {--dry-run : Show what would be removed without saving}', function () {
        // ルール: 0次会(pre)にメンバーが登録されていても、全員のvideo_urlがNULL（=誰の動画的
        // 証拠も無い）場合は「0次会は開催されていない」と判断し、メンバー登録・タイトル・
        // ジャンルごとセクションを削除する。誰か1人でも確認済みvideo_urlがあれば対象外。
        $query = ArchiveSection::query()
            ->where('section_type', 'pre')
            ->with(['onedayarchive', 'members']);

        if ($this->option('date')) {
            $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', $this->option('date')));
        } else {
            if ($this->option('from')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '>=', $this->option('from')));
            }

            if ($this->option('to')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '<=', $this->option('to')));
            }
        }

        $sections = $query->orderBy('onedayarchive_id')->get();
        $removedSections = 0;
        $removedMembers = 0;

        foreach ($sections as $section) {
            if ($section->members->isEmpty()) {
                continue;
            }

            $hasEvidence = $section->members->contains(fn (Member $m) => !empty($m->pivot->video_url));

            if ($hasEvidence) {
                continue;
            }

            $date = $section->onedayarchive?->event_date ?? '-';
            $names = $section->members->pluck('name')->implode('/');

            $this->line("{$date} section={$section->id}: 証拠ゼロのため削除（title={$section->section_title} genre={$section->game_genre} members={$names}）");

            $removedMembers += $section->members->count();

            if (!$this->option('dry-run')) {
                $section->members()->detach();
                $section->delete();
            }

            $removedSections++;
        }

        $this->info("removed_sections={$removedSections} removed_member_rows={$removedMembers}");

        return 0;
    })->purpose('Delete pre(0次会) sections where no member has any confirmed video evidence');

Artisan::command('archives:prune-sections-without-main-members
    {--date= : Target date in YYYY-MM-DD}
    {--from= : Start date in YYYY-MM-DD}
    {--to= : End date in YYYY-MM-DD}
    {--detach : Actually detach the members and delete the section (default: report only)}', function () {
        // youtube:sync-hashtag-archives が「メインメンバー2人以上」ルールを適用していなかった
        // 過去の実行分で、サブ/ゲストメンバー1人だけの動画から誤ってセクションが新規作成されて
        // しまっているケースを検出・掃除するための監査コマンド。デフォルトはレポートのみで、
        // 実際に削除するには --detach を明示する。
        $query = ArchiveSection::query()
            ->whereIn('section_type', array_keys(SectionTimeWindows::WINDOWS_MINUTES))
            ->with(['onedayarchive', 'members'])
            ->whereHas('members');

        if ($this->option('date')) {
            $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', $this->option('date')));
        } else {
            if ($this->option('from')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '>=', $this->option('from')));
            }

            if ($this->option('to')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '<=', $this->option('to')));
            }
        }

        $sections = $query->orderBy('onedayarchive_id')->get();
        $flagged = 0;
        $removedSections = 0;

        foreach ($sections as $section) {
            $mainMemberCount = $section->members
                ->filter(fn (Member $m) => Member::isMainMemberName($m->name))
                ->count();

            if ($mainMemberCount >= 2) {
                continue;
            }

            $flagged++;

            $date = $section->onedayarchive?->event_date ?? '-';
            $names = $section->members->pluck('name')->implode('/');

            $this->line(sprintf(
                '%s %s(%s): メインメンバー%d人のみ / members=%s%s',
                $date,
                $section->section_title ?: $section->section_type,
                $section->game_genre ?: 'ジャンル不明',
                $mainMemberCount,
                $names,
                $this->option('detach') ? ' [削除]' : ''
            ));

            if ($this->option('detach')) {
                $section->members()->detach();
                $section->delete();
                $removedSections++;
            }
        }

        $this->info("flagged={$flagged} removed_sections={$removedSections}");

        return 0;
    })->purpose('Report (or remove) archive sections that do not meet the 2+ main-member registration rule');

Artisan::command('archives:resolve-expired-twitch-archives
    {--member= : Member ID or exact member name}
    {--days=60 : 何日以上前の配信を対象にするか（Twitchはこの日数を超えるとVODが自動削除されるため）}
    {--dry-run : Show what would be updated without saving}', function (
        TwitchArchiveFetcher $twitchFetcher,
        YoutubeArchiveFetcher $youtubeFetcher
    ) {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $thresholdDate = Carbon::now($timezone)->subDays((int) $this->option('days'))->toDateString();

        $query = ArchiveSection::query()
            ->with(['onedayarchive', 'members'])
            ->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '<=', $thresholdDate))
            ->whereHas('members', function ($query) {
                $query->whereNotNull('members.twitch_url')
                    ->where('members.twitch_url', '<>', '')
                    ->whereNull('archive_section_member.video_url')
                    ->where('archive_section_member.no_stream', false)
                    ->where('archive_section_member.url_expired', false);
            });

        $memberOption = $this->option('member');

        if ($memberOption) {
            $query->whereHas('members', function ($query) use ($memberOption) {
                if (ctype_digit($memberOption)) {
                    $query->where('members.id', (int) $memberOption);
                } else {
                    $query->where('members.name', $memberOption);
                }
            });
        }

        $sections = $query->orderBy('onedayarchive_id')->get();

        $resolvedTwitch = 0;
        $resolvedYoutube = 0;
        $markedExpired = 0;
        $skippedNoWindow = 0;

        foreach ($sections as $section) {
            $eventDate = $section->onedayarchive?->event_date;

            if (!$eventDate) {
                continue;
            }

            // 特別回など時間帯の定義が無いsection_typeは、Twitch/YouTube動画との
            // 時間重なり判定ができないため対象外にする（人手確認に任せる）。
            if (!array_key_exists($section->section_type, SectionTimeWindows::WINDOWS_MINUTES)) {
                $skippedNoWindow++;
                continue;
            }

            $date = Carbon::parse($eventDate, $timezone)->startOfDay();
            $from = $date->copy()->subDay()->startOfDay();
            $to = $date->copy()->addDay()->endOfDay();

            $targetMembers = $section->members->filter(
                fn (Member $member) => !empty($member->twitch_url)
                    && empty($member->pivot->video_url)
                    && !$member->pivot->no_stream
                    && !$member->pivot->url_expired
            )->when($memberOption, function ($members) use ($memberOption) {
                return $members->filter(
                    fn (Member $member) => ctype_digit($memberOption)
                        ? $member->id === (int) $memberOption
                        : $member->name === $memberOption
                );
            });

            foreach ($targetMembers as $member) {
                $video = matchArchiveSectionVideo($twitchFetcher, $member, $section, $date, $from, $to)
                    ?? matchArchiveSectionVideo($youtubeFetcher, $member, $section, $date, $from, $to);

                if ($video) {
                    $platformLabel = $video['platform'] === 'twitch' ? 'Twitch' : 'YouTube';

                    $this->line(sprintf(
                        '%s %s: %s を%sで復旧 / %s',
                        $eventDate,
                        $section->section_title ?: $section->section_type,
                        $member->name,
                        $platformLabel,
                        $video['url']
                    ));

                    if (!$this->option('dry-run')) {
                        $section->members()->updateExistingPivot($member->id, [
                            'video_url' => $video['url'],
                            'url_expired' => false,
                            'needs_review' => false,
                            'review_reason' => null,
                        ]);
                    }

                    $video['platform'] === 'twitch' ? $resolvedTwitch++ : $resolvedYoutube++;
                    continue;
                }

                $this->line(sprintf(
                    '%s %s: %s はTwitch/YouTubeどちらにも見つからないためURL有効期限切れとして記録します',
                    $eventDate,
                    $section->section_title ?: $section->section_type,
                    $member->name
                ));

                if (!$this->option('dry-run')) {
                    $section->members()->updateExistingPivot($member->id, [
                        'video_url' => null,
                        'url_expired' => true,
                        'needs_review' => false,
                        'review_reason' => null,
                    ]);
                }

                $markedExpired++;
            }
        }

        $this->info(sprintf(
            'twitch_resolved=%d youtube_resolved=%d marked_expired=%d skipped_no_window=%d',
            $resolvedTwitch,
            $resolvedYoutube,
            $markedExpired,
            $skippedNoWindow
        ));

        return 0;
    })->purpose('Resolve missing Twitch archive URLs older than N days via Twitch API then YouTube API fallback, marking as expired if neither is found');

/**
 * $fetcherが対応するプラットフォームで$memberの候補動画を取得し、$sectionの時間帯と
 * 一定時間以上重なるものが1件だけ見つかればそれを返す。0件・複数件の場合はnullを返し、
 * 呼び出し側で「見つからなかった」扱いにする（誤爆防止のため、曖昧な場合は採用しない）。
 */
function matchArchiveSectionVideo(
    PlatformArchiveFetcher $fetcher,
    Member $member,
    ArchiveSection $section,
    Carbon $date,
    Carbon $from,
    Carbon $to
): ?array {
    if (!$fetcher->supports($member)) {
        return null;
    }

    try {
        $videos = $fetcher->fetchCandidates($member, $from, $to, true);
    } catch (Throwable $exception) {
        return null;
    }

    $overlapping = $videos->filter(function (array $video) use ($section, $date) {
        $start = $video['published_at'];
        $end = isset($video['duration_seconds'])
            ? $start->copy()->addSeconds($video['duration_seconds'])
            : $start;

        return SectionTimeWindows::overlapsWindow($section->section_type, $date, $start, $end);
    })->values();

    return $overlapping->count() === 1 ? $overlapping->first() : null;
}

Artisan::command('archives:autofill-game-genres
    {--from= : Start date in YYYY-MM-DD}
    {--to= : End date in YYYY-MM-DD}
    {--overwrite-amongus : Re-detect sections currently marked as Among Us}
    {--dry-run : Show what would be updated without saving}', function (YoutubeMemberArchiveSyncService $youtubeService, GameGenreInferenceService $gameGenreInference) {
        $query = ArchiveSection::query()
            ->with(['onedayarchive', 'members'])
            ->whereHas('members', fn ($query) => $query->whereNotNull('archive_section_member.video_url'));

        if ($this->option('from')) {
            $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '>=', $this->option('from')));
        }

        if ($this->option('to')) {
            $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '<=', $this->option('to')));
        }

        if (!$this->option('overwrite-amongus')) {
            $query->where(function ($query) {
                $query->whereNull('game_genre')->orWhere('game_genre', '');
            });
        } else {
            $query->where(function ($query) {
                $query->whereNull('game_genre')
                    ->orWhere('game_genre', '')
                    ->orWhere('game_genre', 'Among Us');
            });
        }

        $sections = $query->orderBy('onedayarchive_id')->get();
        $updated = 0;
        $skipped = 0;

        $this->info(sprintf(
            'Scanning %d section(s), overwrite-amongus=%s, dry-run=%s',
            $sections->count(),
            $this->option('overwrite-amongus') ? 'yes' : 'no',
            $this->option('dry-run') ? 'yes' : 'no'
        ));

        foreach ($sections as $section) {
            $urls = $section->members
                ->pluck('pivot.video_url')
                ->filter()
                ->values()
                ->all();

            if (empty($urls)) {
                $skipped++;
                continue;
            }

            try {
                $videos = $youtubeService->fetchVideosByUrls($urls);
            } catch (Throwable $exception) {
                $this->error("section {$section->id}: {$exception->getMessage()}");
                $skipped++;
                continue;
            }

            $gameGenre = $gameGenreInference->inferFromVideos($videos);

            if (!$gameGenre || $section->game_genre === $gameGenre) {
                $skipped++;
                continue;
            }

            $date = $section->onedayarchive?->event_date ?? '-';
            $this->line(sprintf(
                '%s %s: %s -> %s',
                $date,
                $section->section_title ?: $section->section_type,
                $section->game_genre ?: 'blank',
                $gameGenre
            ));

            if (!$this->option('dry-run')) {
                $section->update(['game_genre' => $gameGenre]);
            }

            $updated++;
        }

        $this->info("updated={$updated} skipped={$skipped}");

        return 0;
    })->purpose('Infer archive section game genres from registered YouTube video URLs');

Artisan::command('amongus:analyze-primary-videos
    {--date= : Target archive date in YYYY-MM-DD}
    {--from= : Start archive date in YYYY-MM-DD}
    {--to= : End archive date in YYYY-MM-DD}
    {--use-whisper : Download audio and use local Whisper when subtitles are unavailable}
    {--overwrite-drafts : Replace existing analysis drafts for target sections}
    {--script= : Python analyzer script path}', function () {
        $timezone = config('app.timezone', 'Asia/Tokyo');

        $query = ArchiveSection::query()
            ->with(['onedayarchive', 'members'])
            ->where('section_type', 'primary')
            ->whereRaw('LOWER(game_genre) = ?', ['among us'])
            ->whereHas('members', fn ($query) => $query->whereNotNull('archive_section_member.video_url'));

        if ($this->option('date')) {
            $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', $this->option('date')));
        } else {
            if ($this->option('from')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '>=', $this->option('from')));
            }

            if ($this->option('to')) {
                $query->whereHas('onedayarchive', fn ($query) => $query->whereDate('event_date', '<=', $this->option('to')));
            }
        }

        $sections = $query->orderBy('onedayarchive_id')->get();

        if ($sections->isEmpty()) {
            $this->warn('解析対象の一次会 Among Us がありません。');

            return 0;
        }

        if ($this->option('overwrite-drafts')) {
            AmongusAnalysisDraft::whereIn('archive_section_id', $sections->pluck('id'))->delete();
        }

        $sources = [];

        foreach ($sections as $section) {
            $eventDate = Carbon::parse($section->onedayarchive->event_date, $timezone);
            $estimatedStartAt = $eventDate->copy()->setTime(21, 0);

            foreach ($section->members as $member) {
                $videoUrl = $member->pivot->video_url ?? null;

                if (!$videoUrl) {
                    continue;
                }

                $sources[] = [
                    'archive_section_id' => $section->id,
                    'event_date' => $eventDate->toDateString(),
                    'estimated_start_at' => $estimatedStartAt->toDateTimeString(),
                    'source_member_id' => $member->id,
                    'source_member_name' => $member->name,
                    'source_video_url' => $videoUrl,
                    'participants' => $section->members
                        ->map(fn (Member $participant) => [
                            'id' => $participant->id,
                            'name' => $participant->name,
                        ])
                        ->values()
                        ->all(),
                ];
            }
        }

        if (empty($sources)) {
            $this->warn('解析対象の動画URLがありません。');

            return 0;
        }

        $payload = [
            'generated_at' => Carbon::now($timezone)->toDateTimeString(),
            'analysis_options' => [
                'use_whisper' => (bool) $this->option('use-whisper'),
            ],
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['name', 'type'])
                ->map(fn (Role $role) => [
                    'name' => $role->name,
                    'type' => $role->type,
                ])
                ->values()
                ->all(),
            'sources' => $sources,
        ];

        $scriptPath = $this->option('script') ?: base_path('scripts/amongus/analyze_video.py');
        $inputPath = tempnam(sys_get_temp_dir(), 'amongus-analysis-input-');
        file_put_contents($inputPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $analyzerResult = null;

        if (is_file($scriptPath)) {
            $process = Process::timeout(300)->run(['python3', $scriptPath, $inputPath]);

            if ($process->successful()) {
                $analyzerResult = json_decode($process->output(), true);
            } else {
                $this->warn('Python解析に失敗したため、解析待ちの下書きを作成します。');
                $this->warn(trim($process->errorOutput()) ?: trim($process->output()));
            }
        } else {
            $this->warn("Python解析スクリプトが見つかりません: {$scriptPath}");
        }

        @unlink($inputPath);

        $draftRows = is_array($analyzerResult)
            ? ($analyzerResult['drafts'] ?? [])
            : [];

        if (empty($draftRows)) {
            $draftRows = makePendingAmongusAnalysisDraftRows($sources);
        }

        $created = 0;
        $skipped = 0;

        foreach ($draftRows as $row) {
            $exists = AmongusAnalysisDraft::query()
                ->where('archive_section_id', $row['archive_section_id'])
                ->where('member_id', $row['member_id'] ?? null)
                ->where('video_url', $row['video_url'])
                ->where('match_number', $row['match_number'] ?? null)
                ->where('member_name', $row['member_name'] ?? null)
                ->exists();

            if ($exists && !$this->option('overwrite-drafts')) {
                $skipped++;
                continue;
            }

            AmongusAnalysisDraft::updateOrCreate([
                'archive_section_id' => $row['archive_section_id'],
                'member_id' => $row['member_id'] ?? null,
                'video_url' => $row['video_url'],
                'match_number' => $row['match_number'] ?? null,
                'member_name' => $row['member_name'] ?? null,
            ], [
                'video_timestamp_seconds' => $row['video_timestamp_seconds'] ?? null,
                'video_timestamp_label' => $row['video_timestamp_label'] ?? null,
                'estimated_real_time' => $row['estimated_real_time'] ?? null,
                'role_name' => $row['role_name'] ?? null,
                'result' => $row['result'] ?? null,
                'win_side' => $row['win_side'] ?? null,
                'evidence_text' => $row['evidence_text'] ?? null,
                'confidence' => $row['confidence'] ?? null,
                'status' => $row['status'] ?? AmongusAnalysisDraft::STATUS_PENDING,
                'memo' => $row['memo'] ?? null,
                'raw_payload' => $row['raw_payload'] ?? $row,
            ]);

            $created++;
        }

        $this->info("drafts_created_or_updated={$created} skipped={$skipped}");

        return 0;
    })->purpose('Create reviewable Among Us result analysis drafts from primary Among Us archive videos');

function inferArchiveSectionFromVideo(array $video, string $searchText): array
{
    $timezone = config('app.timezone', 'Asia/Tokyo');
    $startedAt = $video['published_at']->copy()->timezone($timezone);

    // 「特別」単独だと「特別役職」等の通常配信の定型文にも誤反応するため、
    // 複合語のみで判定する。
    $specialWords = ['特別回', '特別配信', '外部大会', '対抗戦', '合同企画', 'スペシャル', '大会', '杯'];
    $matchesSpecialKeyword = collect($specialWords)->contains(
        fn (string $keyword) => str_contains($searchText, mb_strtolower($keyword))
    );

    // 配信開始が21時より前、かつ3時間以上の長時間配信は特別回扱いにする。
    // (21時以降開始の長時間配信は通常回とし、複数の時間帯にまたがる動画として扱う)
    $startMinutes = ((int) $startedAt->format('H')) * 60 + (int) $startedAt->format('i');
    $startsBeforePrimary = $startMinutes < 21 * 60;
    $isLongStream = ($video['duration_seconds'] ?? null) !== null && $video['duration_seconds'] >= 3 * 3600;

    if ($matchesSpecialKeyword || ($startsBeforePrimary && $isLongStream)) {
        return [$startedAt->toDateString(), 'special', '特別回'];
    }

    $archiveDate = $startedAt->copy();

    if ((int) $startedAt->format('H') < 6) {
        $archiveDate->subDay();
    }

    $dayStart = $archiveDate->copy()->startOfDay();
    $minutes = $dayStart->diffInMinutes($startedAt, false);

    if ($minutes < (20 * 60 + 30)) {
        return [$archiveDate->toDateString(), 'pre', '0次会'];
    }

    if ($minutes < (23 * 60 + 30)) {
        return [$archiveDate->toDateString(), 'primary', '1次会'];
    }

    if ($minutes < (25 * 60 + 30)) {
        return [$archiveDate->toDateString(), 'secondary', '2次会'];
    }

    if ($minutes < (27 * 60 + 30)) {
        return [$archiveDate->toDateString(), 'third', '3次会'];
    }

    return [$archiveDate->toDateString(), 'fourth', '4次会'];
}

function makePendingAmongusAnalysisDraftRows(array $sources): array
{
    return collect($sources)
        ->map(function (array $source) {
            return [
                'archive_section_id' => $source['archive_section_id'],
                'member_id' => $source['source_member_id'] ?? null,
                'video_url' => $source['source_video_url'],
                'match_number' => null,
                'video_timestamp_seconds' => 0,
                'video_timestamp_label' => '00:00:00',
                'estimated_real_time' => $source['estimated_start_at'] ?? null,
                'member_name' => null,
                'role_name' => null,
                'result' => null,
                'win_side' => null,
                'evidence_text' => '動画解析は未実行です。動画URLと推定開始時刻を確認し、Python解析結果で更新してください。',
                'confidence' => 0,
                'status' => AmongusAnalysisDraft::STATUS_PENDING,
                'memo' => null,
                'raw_payload' => $source,
            ];
        })
        ->values()
        ->all();
}

function hasArujanKeywordInTitleOrDescription(array $video): bool
{
    $searchText = mb_strtolower(implode("\n", [
        $video['title'] ?? '',
        $video['description'] ?? '',
    ]));

    foreach (['#アルジャン', 'アルジャン', '#あるじゃん', 'あるじゃん'] as $keyword) {
        if (str_contains($searchText, mb_strtolower($keyword))) {
            return true;
        }
    }

    return false;
}

function isShortsVideo(array $video): bool
{
    $title = mb_strtolower($video['title'] ?? '');
    $description = mb_strtolower($video['description'] ?? '');
    $durationSeconds = $video['duration_seconds'] ?? null;

    return str_contains($title, '#shorts')
        || str_contains($title, 'youtube shorts')
        || str_contains($description, '#shorts')
        || ($durationSeconds !== null && $durationSeconds <= 180);
}

function isLiveArchiveVideo(array $video): bool
{
    return ($video['is_live_archive'] ?? false) === true;
}

function isYoutubeUrl(?string $url): bool
{
    if (!$url) {
        return false;
    }

    return str_contains($url, 'youtube.com')
        || str_contains($url, 'youtu.be');
}

} // function_exists('extractParticipantNamesFromDescription')
