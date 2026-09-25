<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeArchiveParticipantsJob;
use App\Models\ArchiveSectionAbsentMember;
use App\Models\Onedayarchive;
use App\Models\ArchiveSection;
use App\Models\Member;
use App\Models\Game;
use App\Rules\OfficialYoutubeChannelUrl;
use App\Services\Archive\ParticipantComputationService;
use App\Services\GameGenreInferenceService;
use App\Services\YoutubeMemberArchiveSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminArchiveController extends Controller
{
    private const SECTION_CONFIGS = [
        'pre' => '0次会',
        'primary' => '1次会',
        'secondary' => '2次会',
        'third' => '3次会',
        'fourth' => '4次会',
    ];

    public function index(Request $request)
    {
        if ($request->filled('year') && $request->filled('monthOnly')) {
            $month = sprintf('%04d-%02d', (int) $request->input('year'), (int) $request->input('monthOnly'));
        } else {
            $month = $request->input('month', now()->format('Y-m'));
        }

        $currentMonth = Carbon::parse($month . '-01');
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $startWeekday = $startOfMonth->dayOfWeek; // 0:日曜, 1:月曜 ...

        $archives = Onedayarchive::with(['sections.members' => function ($query) {
        $query->withPivot('needs_review');
    }])
    ->get()
    ->keyBy('event_date');

$registeredGameNames = Game::pluck('name');

$earliestDate = Onedayarchive::min('event_date');
$latestDate = Onedayarchive::max('event_date');

$minYear = min((int) $currentMonth->format('Y'), $earliestDate ? (int) Carbon::parse($earliestDate)->format('Y') : (int) $currentMonth->format('Y'));
$maxYear = max((int) $currentMonth->format('Y'), $latestDate ? (int) Carbon::parse($latestDate)->format('Y') : (int) $currentMonth->format('Y'));

$calendarDays = [];

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = $currentMonth->copy()->day($day)->format('Y-m-d');

    $archive = $archives->get($date);

    $calendarDays[] = [
        'day' => $day,
        'date' => $date,
        'archive' => $archive,
    ];
}
        

        return view('admin.archives.index', compact('month', 'currentMonth', 'calendarDays', 'daysInMonth', 'startWeekday', 'minYear', 'maxYear', 'registeredGameNames'));
    }

    /**
     * カレンダー一覧から「配信なしにする」「取り消す」で、その日の確認状態を切り替える。
     * 「未登録（未確認）」と「配信なし（確認済み）」を区別するために使う。
     */
    public function markNoStream(Request $request, string $date)
    {
        $validated = $request->validate([
            'no_stream' => ['required', 'boolean'],
            'month' => ['nullable', 'string'],
        ]);

        if ($validated['no_stream']) {
            Onedayarchive::updateOrCreate(
                ['event_date' => $date],
                ['no_stream' => true]
            );
        } else {
            $archive = Onedayarchive::where('event_date', $date)->first();

            // セクションに実データが無ければレコードごと削除し、完全に「未登録」へ戻す。
            if ($archive && $archive->sections()->whereHas('members')->doesntExist()) {
                $archive->delete();
            } elseif ($archive) {
                $archive->update(['no_stream' => false]);
            }
        }

        return redirect()
            ->route('admin.archives.index', array_filter(['month' => $validated['month'] ?? null]));
    }

    /**
     * 「仮完了にする」「完了にする」ボタン用。未着手→仮完了→完了の3段階を手動で切り替える。
     * 既に同じステータスが付いている状態でもう一度押すと未着手に戻す（トグル）。
     * 保存(update)処理では変更しないため、内容を編集してもステータスは維持される。
     */
    public function markStatus(Request $request, string $date)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:tentative,done'],
        ]);

        $archive = Onedayarchive::where('event_date', $date)->first();
        $newStatus = ($archive && $archive->status === $validated['status']) ? null : $validated['status'];

        Onedayarchive::updateOrCreate(
            ['event_date' => $date],
            ['status' => $newStatus]
        );

        return redirect()->route('admin.archives.edit', ['date' => $date]);
    }

    public function edit(string $date)
{
    $archive = Onedayarchive::with(['sections.members'])
        ->where('event_date', $date)
        ->first();

    // 特別回が優先されるため、通常アーカイブ編集画面ではこの日付を編集させない。
    if ($archive && $archive->hasSpecialSection()) {
        return redirect()
            ->route('admin.archives.special.index')
            ->with('success', "{$date} は特別回として登録済みのため、通常アーカイブ編集画面では編集できません。下記の一覧から編集してください。");
    }

    if (!$archive) {
        $archive = new Onedayarchive();
        $archive->event_date = $date;
        $archive->official_title = '';
        $archive->official_video_url = '';
        $archive->official_edited_title = '';
        $archive->official_edited_video_url = '';
        $archive->thumbnail_url = '';
        $archive->description = '';
        $archive->setRelation('sections', collect());
    }

    $sectionsByType = collect(self::SECTION_CONFIGS)
        ->mapWithKeys(function (string $label, string $type) use ($archive) {
            $section = $archive->sections->firstWhere('section_type', $type) ?? new ArchiveSection([
                'section_type' => $type,
                'game_genre' => '',
                'section_title' => $label,
            ]);

            if (!$section->exists) {
                $section->setRelation('members', collect());
            }

            return [$type => $section];
        });

    $members = Member::orderBy('name')->get();
    $games = Game::orderBy('name')->get();
    $registeredGameNamesForJs = $games->pluck('name')->values();

    $allMembersForJs = $members->map(fn ($member) => [
        'id' => $member->id,
        'name' => $member->name,
    ])->values();

    $initialSectionMembersForJs = $sectionsByType->map(function (ArchiveSection $section) {
        return $section->members->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'video_url' => $member->pivot->video_url ?? '',
            'needs_review' => (bool) ($member->pivot->needs_review ?? false),
            'review_reason' => $member->pivot->review_reason ?? '',
            'no_stream' => (bool) ($member->pivot->no_stream ?? false),
            'url_expired' => (bool) ($member->pivot->url_expired ?? false),
        ])->values();
    });

    return view('admin.archives.edit', compact(
        'date',
        'archive',
        'sectionsByType',
        'members',
        'games',
        'allMembersForJs',
        'initialSectionMembersForJs',
        'registeredGameNamesForJs'
    ) + [
        'sectionConfigs' => self::SECTION_CONFIGS,
    ]);
}

    public function update(Request $request, string $date)
    {
        $validated = $request->validate([
            'official_title' => ['nullable', 'string', 'max:255'],
            'official_video_url' => ['nullable', 'string', 'max:2048'],
            'official_edited_title' => ['nullable', 'string', 'max:255'],
            'official_edited_video_url' => ['nullable', 'string', 'max:2048', new OfficialYoutubeChannelUrl()],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],

            'sections' => ['nullable', 'array'],
            'sections.*.genre' => ['nullable', 'string', 'max:255'],
            'sections.*.members' => ['nullable', 'array'],
            'sections.*.members.*.selected' => ['nullable'],
            'sections.*.members.*.video_url' => ['nullable', 'string'],
            'sections.*.members.*.no_stream' => ['nullable'],
            'sections.*.members.*.url_expired' => ['nullable'],
        ]);

        $existingArchive = Onedayarchive::where('event_date', $date)->first();

        // 特別回が優先されるため、通常アーカイブ編集画面からの保存はブロックする
        // （フォーム再送信によるバイパス防止。editの入口でも同じチェックをしている）。
        if ($existingArchive && $existingArchive->hasSpecialSection()) {
            return redirect()
                ->route('admin.archives.special.index')
                ->with('success', "{$date} は特別回として登録済みのため、通常アーカイブ編集画面では編集できません。下記の一覧から編集してください。");
        }

        foreach (self::SECTION_CONFIGS as $type => $label) {
            $selectedIds = collect($validated['sections'][$type]['members'] ?? [])
                ->filter(fn (array $data) => !empty($data['selected']))
                ->keys();

            $existingSection = $existingArchive
                ? $existingArchive->sections->firstWhere('section_type', $type)
                : null;
            $isNewRegistration = !$existingSection || $existingSection->members()->doesntExist();

            // 既に参加者がいるセクションの編集（削除等）はブロックせず、
            // 今までメンバーがいなかったセクションへの新規登録だけ人数ルールを適用する。
            // 1次会のみメインメンバー2人以上必須。0次会・2次会以降はハッチャンがいるか、
            // ハッチャンを除いて2人以上いれば登録できる。
            if ($isNewRegistration && $selectedIds->isNotEmpty()) {
                if ($type === 'primary') {
                    if (!Member::hasEnoughMainMembers($selectedIds)) {
                        throw ValidationException::withMessages([
                            "sections.{$type}.members" => "{$label}: アルジャンのメインメンバーが2人以上いないとセクションを登録できません。",
                        ]);
                    }
                } elseif (!Member::hasEnoughMembersOrHacchan($selectedIds)) {
                    throw ValidationException::withMessages([
                        "sections.{$type}.members" => "{$label}: ハッチャンがいないメンバーが1人だけだとセクションを登録できません。",
                    ]);
                }
            }
        }

        $archive = Onedayarchive::updateOrCreate(
            ['event_date' => $date],
            [
                'official_title' => $validated['official_title'] ?? 'なし',
                'official_video_url' => $validated['official_video_url'] ?? '',
                'official_edited_title' => $validated['official_edited_title'] ?? '',
                'official_edited_video_url' => $validated['official_edited_video_url'] ?? '',
                'thumbnail_url' => $validated['thumbnail_url'] ?? '',
                'description' => $validated['description'] ?? '',
                // 実際に内容を保存した時点で「配信なし」ではなくなるため解除する。
                'no_stream' => false,
            ]
        );

        foreach (self::SECTION_CONFIGS as $type => $label) {
            $section = $this->saveSection(
                $archive,
                $type,
                $validated['sections'][$type]['genre'] ?? '',
                $label
            );

            $this->syncSectionMembers($section, $validated['sections'][$type]['members'] ?? []);
        }

        return redirect()
            ->route('admin.archives.edit', ['date' => $date])
            ->with('success', '更新しました。');
    }

    public function autofillGames(
        string $date,
        YoutubeMemberArchiveSyncService $youtubeService,
        GameGenreInferenceService $gameGenreInference
    )
    {
        $archive = Onedayarchive::with(['sections.members'])
            ->where('event_date', $date)
            ->first();

        if (!$archive) {
            return redirect()
                ->route('admin.archives.edit', ['date' => $date])
                ->with('success', '自動入力できるアーカイブがありません。');
        }

        $updated = 0;

        foreach ($archive->sections as $section) {
            if (!empty($section->game_genre) && $section->game_genre !== 'Among Us') {
                continue;
            }

            $urls = $section->members
                ->pluck('pivot.video_url')
                ->filter()
                ->values()
                ->all();

            if (empty($urls)) {
                continue;
            }

            $videos = $youtubeService->fetchVideosByUrls($urls);
            $gameGenre = $gameGenreInference->inferFromVideos($videos);

            if (!$gameGenre || $section->game_genre === $gameGenre) {
                continue;
            }

            $section->update(['game_genre' => $gameGenre]);
            $updated++;
        }

        return redirect()
            ->route('admin.archives.edit', ['date' => $date])
            ->with('success', $updated > 0
                ? "ゲームタイトルを{$updated}件自動入力しました。"
                : '自動入力できるゲームタイトルは見つかりませんでした。');
    }

    /**
     * 「ゲーム内容と参加メンバーを抽出する」ボタン用。
     * まだvideo_urlが登録されていない日でも、アカウント登録済みの全メンバーを横断的に検索し、
     * セクション(0次会〜4次会)ごとの参加者・ゲームジャンル候補と、公式チャンネル（1つに固定）の
     * その日の投稿動画候補を求める（保存は行わず、画面側で各欄に反映するだけ）。
     *
     * 古い日付ほどYouTube/Twitchのページング遡りで数分かかることがあり、Webリクエスト内で
     * 同期実行するとタイムアウトするため、実処理はジョブキューに投げてすぐ202を返す。
     * 画面側はcomputeParticipantsStatusをポーリングして完了を待つ。
     */
    public function computeParticipants(string $date)
    {
        $token = (string) Str::uuid();

        Cache::put(
            ComputeArchiveParticipantsJob::cacheKey($date, $token),
            ['status' => 'pending'],
            now()->addMinutes(30)
        );

        ComputeArchiveParticipantsJob::dispatch($date, $token);

        return response()->json(['token' => $token], 202);
    }

    /**
     * computeParticipantsが投げたジョブの進捗確認用。
     * status: pending（処理中）/ done（完了、sections・officialを含む）/ failed（失敗、messageを含む）。
     */
    public function computeParticipantsStatus(string $date, string $token)
    {
        $result = Cache::get(ComputeArchiveParticipantsJob::cacheKey($date, $token));

        if (!$result) {
            return response()->json([
                'status' => 'failed',
                'message' => '抽出処理の状態が見つかりません。時間を置いて再度お試しください。',
            ], 404);
        }

        return response()->json($result);
    }

    /**
     * 「この動画から参加者を抽出する」ボタン用。既に登録済みの1動画のURLを起点に、その概要欄から
     * 他の参加者候補を検出する（本人の配信で裏付けが取れた候補のみ）。保存は行わず、画面側の
     * 未保存フォーム状態に候補を追加するだけ（compute-participantsと同じ扱い）。
     */
    public function extractParticipantsFromVideo(Request $request, string $date, ParticipantComputationService $service)
    {
        $validated = $request->validate([
            'section_type' => ['required', 'string', 'in:' . implode(',', array_keys(self::SECTION_CONFIGS))],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'video_url' => ['required', 'string', 'max:2048'],
            'existing_member_ids' => ['nullable', 'array'],
            'existing_member_ids.*' => ['integer'],
        ]);

        $timezone = config('app.timezone', 'Asia/Tokyo');
        $eventDayStart = Carbon::parse($date, $timezone)->startOfDay();

        $result = $service->extractParticipantsFromVideoUrl(
            $validated['video_url'],
            $validated['section_type'],
            $eventDayStart,
            (int) $validated['member_id'],
            array_map('intval', $validated['existing_member_ids'] ?? [])
        );

        if (isset($result['error'])) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    /**
     * 「急遽不参加」ボタン用。参加者候補として名前は挙がったが実際には参加しなかったことが
     * 確認できたメンバーを記録する。他メンバーの動画の概要欄に名前が残っている限り、
     * 「参加メンバーを抽出する」を再実行するたびに検出されてしまうため、以後の抽出結果からは
     * 除外する（既にDBへ保存済みのセクション参加者であれば、そちらからも外す）。
     */
    public function markAbsent(Request $request, string $date)
    {
        $validated = $request->validate([
            'section_type' => ['required', 'string', 'in:' . implode(',', array_keys(self::SECTION_CONFIGS))],
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        ArchiveSectionAbsentMember::firstOrCreate([
            'event_date' => $date,
            'section_type' => $validated['section_type'],
            'member_id' => $validated['member_id'],
        ]);

        $archive = Onedayarchive::where('event_date', $date)->first();
        $section = $archive?->sections->firstWhere('section_type', $validated['section_type']);
        $section?->members()->detach($validated['member_id']);

        return response()->json(['ok' => true]);
    }

    /**
     * 「削除」ボタン用。参加者候補として誤って追加されたメンバーをその場でDBからも外す。
     * 「急遽不参加」と異なりArchiveSectionAbsentMemberへの記録は行わないため、
     * 再度「参加メンバーを抽出する」を実行すれば同じ候補が再び挙がりうる。
     */
    public function removeMember(Request $request, string $date)
    {
        $validated = $request->validate([
            'section_type' => ['required', 'string', 'in:' . implode(',', array_keys(self::SECTION_CONFIGS))],
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $archive = Onedayarchive::where('event_date', $date)->first();
        $section = $archive?->sections->firstWhere('section_type', $validated['section_type']);
        $section?->members()->detach($validated['member_id']);

        return response()->json(['ok' => true]);
    }

    private function saveSection(
        Onedayarchive $archive,
        string $sectionType,
        ?string $gameGenre,
        string $sectionTitle = ''
    ): ArchiveSection {
        return ArchiveSection::updateOrCreate(
            [
                'onedayarchive_id' => $archive->id,
                'section_type' => $sectionType,
            ],
            [
                'game_genre' => $gameGenre ?? '',
                'section_title' => $sectionTitle,
            ]
        );
    }

    private function syncSectionMembers(ArchiveSection $section, array $members): void
    {
        $syncData = [];

        foreach ($members as $memberId => $data) {
            $syncData[$memberId] = [
                'video_url' => $data['video_url'] ?? null,
                'no_stream' => !empty($data['no_stream']),
                'url_expired' => !empty($data['url_expired']),
                // 管理画面から保存した時点で確認済みとみなし、要確認フラグを解除する
                'needs_review' => false,
                'review_reason' => null,
            ];
        }

        $section->members()->sync($syncData);
    }
}
