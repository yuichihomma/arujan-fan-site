@extends('layouts.home')

@section('content')

<div class="space-y-8">

    {{-- YouTube --}}
    @if($video)
        <section class="space-y-4">
            <h2 class="border-l-4 border-red-500 pl-3 text-xl font-bold text-gray-800">
                最新の動画
            </h2>

            <a
                href="https://www.youtube.com/watch?v={{ $video['id']['videoId'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="block overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:border-red-200 hover:bg-red-50/40"
            >
                <img
                    src="{{ $video['snippet']['thumbnails']['high']['url'] }}"
                    alt="{{ $video['snippet']['title'] }}"
                    class="aspect-video w-full object-cover"
                >

                <div class="p-4">
                    <p class="text-sm font-semibold text-red-600">YouTube</p>
                    <p class="mt-2 font-medium text-gray-800">
                        {{ $video['snippet']['title'] }}
                    </p>
                </div>
            </a>
        </section>
    @else
        <section class="rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-600">
            動画がまだありません
        </section>
    @endif

    {{-- アルジャンメインメンバーの最新アーカイブ --}}
    <section class="space-y-4 border-t border-gray-200 pt-8">
        <h2 class="border-l-4 border-blue-500 pl-3 text-xl font-bold text-gray-800">
            最新配信アーカイブの参加メンバー
        </h2>

        <div class="flex gap-3 overflow-x-auto pb-2">
            <a href="#" class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-center hover:border-blue-200 hover:bg-blue-50">
                ハッチャン
            </a>
            <a href="#" class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-center hover:border-blue-200 hover:bg-blue-50">
                ポン酢野郎
            </a>
            <a href="#" class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-center hover:border-blue-200 hover:bg-blue-50">
                瀬戸あさひ
            </a>
            <a href="#" class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-center hover:border-blue-200 hover:bg-blue-50">
                なつぴょん
            </a>
            <a href="#" class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-center hover:border-blue-200 hover:bg-blue-50">
                バブルケーキ
            </a>
            <a href="#" class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-center hover:border-blue-200 hover:bg-blue-50">
                はてな
            </a>
            <a href="#" class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-center hover:border-blue-200 hover:bg-blue-50">
                みさとらん
            </a>
        </div>
    </section>

    {{-- メンバー横スクロール --}}
    <section class="border-t border-gray-200 pt-8">
        <h3 class="mb-3 text-sm font-bold text-gray-700">
            メンバーリンク
        </h3>

        <div class="flex gap-3 overflow-x-auto pb-2">
            <a href="#"
               class="min-w-[140px] flex-shrink-0 rounded-lg border border-gray-200 bg-white p-3 text-center hover:border-gray-300 hover:bg-gray-50">
                <p class="text-sm font-semibold">メンバーA</p>
            </a>

            <a href="#"
               class="min-w-[140px] flex-shrink-0 rounded-lg border border-gray-200 bg-white p-3 text-center hover:border-gray-300 hover:bg-gray-50">
                <p class="text-sm font-semibold">メンバーB</p>
            </a>

            <a href="#"
               class="min-w-[140px] flex-shrink-0 rounded-lg border border-gray-200 bg-white p-3 text-center hover:border-gray-300 hover:bg-gray-50">
                <p class="text-sm font-semibold">メンバーC</p>
            </a>
        </div>
    </section>

</div>
@endsection
