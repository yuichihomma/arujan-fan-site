@php
    $youtubeUrl = $member->youtube_url
        ?: ($member->youtube_channel_id ? 'https://www.youtube.com/channel/' . $member->youtube_channel_id : null);

    $links = [
        'YouTube' => $youtubeUrl,
        'X' => $member->x_url,
        'Twitch' => $member->twitch_url,
        ($member->other_platform ?: 'その他') => $member->other_url,
    ];

    $amongusFirstDate = $member->amongus_first_participation_date;
    $amongusLatestDate = $member->amongus_latest_participation_date;
@endphp

<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex items-start gap-4">
        @if($member->avatar)
            <img
                src="{{ $member->avatar }}"
                alt="{{ $member->name }}"
                class="h-16 max-h-16 min-h-16 w-16 min-w-16 max-w-16 shrink-0 rounded-full object-cover"
                style="width: 64px; height: 64px; min-width: 64px; max-width: 64px; min-height: 64px; max-height: 64px; object-fit: cover;"
            >
        @else
            <div class="flex h-16 max-h-16 min-h-16 w-16 min-w-16 max-w-16 shrink-0 items-center justify-center rounded-full {{ $avatarClass }} text-lg font-bold">
                {{ mb_substr($member->name, 0, 1) }}
            </div>
        @endif

        <div class="min-w-0">
            <h3 class="break-words text-lg font-bold text-slate-800">
                {{ $member->name }}
            </h3>
        </div>
    </div>

    @if($member->description)
        <p class="mt-4 line-clamp-4 text-sm leading-6 text-slate-600">
            {{ $member->description }}
        </p>
    @else
        <p class="mt-4 text-sm text-slate-400">
            紹介文は準備中です
        </p>
    @endif

    @if($amongusFirstDate || $amongusLatestDate)
        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs font-bold text-slate-500">Among Us初参加</p>
                <p class="mt-1 font-bold text-slate-800">
                    {{ $amongusFirstDate ? \Carbon\Carbon::parse($amongusFirstDate)->format('Y/m/d') : '未参加' }}
                </p>
            </div>

            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs font-bold text-slate-500">Among Us最新参加</p>
                <p class="mt-1 font-bold text-slate-800">
                    {{ $amongusLatestDate ? \Carbon\Carbon::parse($amongusLatestDate)->format('Y/m/d') : '未参加' }}
                </p>
            </div>
        </div>
    @endif

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach($links as $label => $url)
            @if($url)
                <a
                    href="{{ $url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100"
                >
                    {{ $label }}
                </a>
            @endif
        @endforeach
    </div>
</article>
