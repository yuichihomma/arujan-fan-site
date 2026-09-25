<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\YoutubeMemberArchiveSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->input('keyword');
        $editingMember = null;

        if ($request->filled('edit')) {
            $editingMember = Member::find($request->input('edit'));
        }

        $members = Member::query()
            ->when($keyword, function ($query, $keyword) {
                $query->where(
                    'name',
                    'like',
                    "%{$keyword}%"
                );
            })
            ->orderBy('id')
            ->get();

        return view(
            'admin.members.index',
            compact('members', 'keyword', 'editingMember')
        );
    }

    public function store(Request $request, YoutubeMemberArchiveSyncService $youtubeService)
    {
        $data = $this->validatedMemberData($request);
        $syncWarning = null;

        $this->applyYoutubeProfileFromUrl($data, $youtubeService, $syncWarning);
        $this->applyArujanChannelIdFromUrl($data, $youtubeService, $syncWarning);

        Member::create($data);

        return redirect()
            ->route('admin.members.index')
            ->with($syncWarning ? ['warning' => $syncWarning] : []);
    }

    public function update(Request $request, Member $member, YoutubeMemberArchiveSyncService $youtubeService)
    {
        $validator = Validator::make($request->all(), $this->memberDataRules());

        if ($validator->fails()) {
            return redirect()
                ->route('admin.members.index', ['edit' => $member->id])
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $syncWarning = null;

        $youtubeUrlChanged = ($data['youtube_url'] ?? null) !== $member->youtube_url;

        if ($youtubeUrlChanged || empty($data['youtube_channel_id'])) {
            $this->applyYoutubeProfileFromUrl($data, $youtubeService, $syncWarning);
        } elseif (!empty($data['youtube_channel_id']) && empty($data['avatar'])) {
            $this->applyYoutubeProfileFromChannelId($data, $youtubeService, $syncWarning);
        }

        $arujanUrlChanged = ($data['arujan_youtube_url'] ?? null) !== $member->arujan_youtube_url;

        if ($arujanUrlChanged || empty($data['arujan_youtube_channel_id'])) {
            $this->applyArujanChannelIdFromUrl($data, $youtubeService, $syncWarning);
        }

        $member->update($data);

        return redirect()
            ->route('admin.members.index', ['edit' => $member->id])
            ->with($syncWarning ? ['warning' => $syncWarning] : ['success' => '更新しました。']);
    }

    private function validatedMemberData(Request $request): array
    {
        return $request->validate($this->memberDataRules());
    }

    private function memberDataRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'youtube_url' => ['nullable', 'string', 'max:2048'],
            'youtube_channel_id' => ['nullable', 'string', 'max:255'],
            'arujan_youtube_url' => ['nullable', 'string', 'max:2048'],
            'arujan_youtube_channel_id' => ['nullable', 'string', 'max:255'],
            'x_url' => ['nullable', 'string', 'max:2048'],
            'twitch_url' => ['nullable', 'string', 'max:2048'],
            'other_platform' => ['nullable', 'string', 'max:255'],
            'other_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    private function applyYoutubeProfileFromUrl(
        array &$data,
        YoutubeMemberArchiveSyncService $youtubeService,
        ?string &$syncWarning
    ): void {
        if (empty($data['youtube_url'])) {
            return;
        }

        try {
            $channelId = $youtubeService->resolveChannelId($data['youtube_url']);

            if (!$channelId) {
                $syncWarning = 'YouTube URLからチャンネルIDを取得できませんでした。';
                return;
            }

            $data['youtube_channel_id'] = $channelId;
            $this->applyYoutubeProfileFromChannelId($data, $youtubeService, $syncWarning);
        } catch (Throwable $exception) {
            $syncWarning = 'YouTube情報の自動更新に失敗しました: ' . $exception->getMessage();
        }
    }

    /**
     * アルジャン配信チャンネルURLからチャンネルIDだけを解決する
     * （アイコン等のプロフィールはメインチャンネル側を正とするため更新しない）。
     */
    private function applyArujanChannelIdFromUrl(
        array &$data,
        YoutubeMemberArchiveSyncService $youtubeService,
        ?string &$syncWarning
    ): void {
        if (empty($data['arujan_youtube_url'])) {
            $data['arujan_youtube_channel_id'] = null;
            return;
        }

        try {
            $channelId = $youtubeService->resolveChannelId($data['arujan_youtube_url']);

            if (!$channelId) {
                $syncWarning = trim(($syncWarning ? $syncWarning . ' / ' : '')
                    . 'アルジャン配信チャンネルURLからチャンネルIDを取得できませんでした。');
                return;
            }

            $data['arujan_youtube_channel_id'] = $channelId;
        } catch (Throwable $exception) {
            $syncWarning = trim(($syncWarning ? $syncWarning . ' / ' : '')
                . 'アルジャン配信チャンネルIDの自動更新に失敗しました: ' . $exception->getMessage());
        }
    }

    private function applyYoutubeProfileFromChannelId(
        array &$data,
        YoutubeMemberArchiveSyncService $youtubeService,
        ?string &$syncWarning
    ): void {
        if (empty($data['youtube_channel_id'])) {
            return;
        }

        try {
            $profile = $youtubeService->fetchChannelProfile($data['youtube_channel_id']);
            $thumbnailUrl = $profile['thumbnail_url'] ?? null;

            if ($thumbnailUrl) {
                $data['avatar'] = $thumbnailUrl;
            }
        } catch (Throwable $exception) {
            $syncWarning = 'YouTubeアイコンの自動更新に失敗しました: ' . $exception->getMessage();
        }
    }
}
