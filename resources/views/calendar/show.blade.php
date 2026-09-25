@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800">
            アーカイブ検索（1日の詳細）
        </h1>

        <a href="{{ route('calendar.index', ['month' => \Carbon\Carbon::parse($selectedDate)->format('Y-m')]) }}"
           class="rounded-full border border-gray-300 px-5 py-2 text-sm text-gray-700 hover:bg-gray-50">
            カレンダーに戻る
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <h2 class="mb-6 text-center text-4xl font-bold text-gray-800">
            {{ \Carbon\Carbon::parse($selectedDate)->format('Y/m/d') }}
        </h2>

        @if($streams->isEmpty())
            <p class="text-center text-gray-500">この日のアーカイブは登録されていません。</p>
        @else
            @php
                $firstStream = $streams->first();
            @endphp

            <div class="overflow-hidden rounded-xl border border-gray-300">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border border-gray-300 px-4 py-3 text-left">区分</th>
                            <th class="border border-gray-300 px-4 py-3 text-left">タイトル / ジャンル</th>
                            <th class="border border-gray-300 px-4 py-3 text-left">参加者</th>
                            <th class="border border-gray-300 px-4 py-3 text-left">メンバー別動画</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($firstStream && (!empty($firstStream->official_edited_title) || !empty($firstStream->official_edited_video_url)))
                            <tr>
                                <td class="border border-gray-300 px-4 py-4 align-top font-semibold text-gray-700">
                                    公式編集動画
                                </td>

                                <td class="border border-gray-300 px-4 py-4 align-top text-gray-700">
                                    {{ $firstStream->official_edited_title ?: '未設定' }}
                                </td>

                                <td class="border border-gray-300 px-4 py-4 align-top text-gray-400">
                                    -
                                </td>

                                <td class="border border-gray-300 px-4 py-4 align-top text-gray-700">
                                    @if(!empty($firstStream->official_edited_video_url))
                                        <a href="{{ $firstStream->official_edited_video_url }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="break-all text-blue-600 underline">
                                            {{ $firstStream->official_edited_video_url }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">URL未登録</span>
                                    @endif
                                </td>
                            </tr>
                        @endif

                        {{-- 0次会・1次会・2次会・3次会・特別回 --}}
                        @forelse($streams as $stream)
                            @forelse($stream->sections as $section)
                                @php
                                    $sectionLabel = match ($section->section_type) {
                                        'pre' => '0次会',
                                        'primary' => '1次会',
                                        'secondary' => '2次会',
                                        'third' => '3次会',
                                        'fourth' => '4次会',
                                        'special' => '特別回',
                                        default => '区分未設定',
                                    };
                                @endphp
                                <tr>
                                    <td class="border border-gray-300 px-4 py-4 align-top font-semibold text-gray-700">
                                        {{ $sectionLabel }}
                                    </td>

                                    <td class="border border-gray-300 px-4 py-4 align-top text-gray-700">
                                        <div class="space-y-1">
                                            @if(!empty($section->game_genre))
                                                <p>{{ $section->game_genre }}</p>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="border border-gray-300 px-4 py-4 align-top text-gray-700">
                                        @if($section->members->isNotEmpty())
                                            <ul class="space-y-2">
                                                @foreach($section->members as $member)
                                                    <li>
                                                        <span class="font-semibold">{{ $member->name }}</span>
                                                        @if($member->youtube_url || $member->youtube_channel_id)
                                                            <a
                                                                href="{{ $member->youtube_url ?: 'https://www.youtube.com/channel/' . $member->youtube_channel_id }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="ml-2 text-xs text-red-600 underline"
                                                            >
                                                                チャンネル
                                                            </a>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-gray-400">参加者なし</span>
                                        @endif
                                    </td>

                                    <td class="border border-gray-300 px-4 py-4 align-top text-gray-700">
                                        @if($section->members->isNotEmpty())
                                            <div class="space-y-2">
                                                @foreach($section->members as $member)
                                                    @php
                                                        $archiveUrl = $member->pivot->video_url ?? null;
                                                        $urlExpired = (bool) ($member->pivot->url_expired ?? false);
                                                    @endphp

                                                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                                                        <p class="mb-1 text-xs font-bold text-gray-500">
                                                            {{ $member->name }}
                                                        </p>

                                                        @if($archiveUrl)
                                                            <a href="{{ $archiveUrl }}"
                                                               target="_blank"
                                                               rel="noopener noreferrer"
                                                               class="block break-all text-blue-600 underline">
                                                                {{ $archiveUrl }}
                                                            </a>
                                                        @elseif($urlExpired)
                                                            <span class="block text-gray-500">URL有効期限切れのためアーカイブなし</span>
                                                        @else
                                                            <span class="text-gray-400">アーカイブURL未登録</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-400">URL未登録</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="border border-gray-300 px-4 py-6 text-center text-gray-500">
                                        セクションが登録されていません。
                                    </td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="4" class="border border-gray-300 px-4 py-6 text-center text-gray-500">
                                    この日の配信はありません。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- 日ごとのコメント欄を今後出したい場合 --}}
            <div class="mt-8">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">この日はどんなことが起きた？</h3>

                <div class="overflow-hidden rounded-xl border border-gray-300">
                    <table class="w-full border-collapse text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border border-gray-300 px-4 py-3 text-left">ユーザー名</th>
                                <th class="border border-gray-300 px-4 py-3 text-left">コメント</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-gray-300 px-4 py-4 text-gray-400">未設定</td>
                                <td class="border border-gray-300 px-4 py-4 text-gray-400">コメント機能を後で接続</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-8 flex items-center justify-center gap-6 text-lg font-semibold text-gray-700">
                @php
                    $prevDate = \Carbon\Carbon::parse($selectedDate)->copy()->subDay()->format('Y-m-d');
                    $nextDate = \Carbon\Carbon::parse($selectedDate)->copy()->addDay()->format('Y-m-d');
                @endphp

                <a href="{{ route('calendar.show', ['date' => $prevDate]) }}"
                   class="hover:text-violet-600">
                    ← 前日
                </a>

                <a href="{{ route('calendar.index', ['month' => \Carbon\Carbon::parse($selectedDate)->format('Y-m')]) }}"
                   class="hover:text-violet-600">
                    カレンダーに戻る
                </a>

                <a href="{{ route('calendar.show', ['date' => $nextDate]) }}"
                   class="hover:text-violet-600">
                    翌日 →
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
