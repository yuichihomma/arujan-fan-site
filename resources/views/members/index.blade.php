{{-- resources/views/members/index.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-50 px-6 py-10">
    <div class="mx-auto max-w-6xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">

        {{-- タイトル --}}
        <div class="mb-10 text-center">
            <h1 class="text-3xl font-bold text-slate-800">
                参加者一覧
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                アルジャンに参加しているメンバー・ゲストを紹介します
            </p>
        </div>

        {{-- メインメンバー --}}
        <section class="mb-12">
            <h2 class="mb-5 text-xl font-bold text-slate-800">
                メインメンバー紹介
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($mainMembers as $member)
                    @include('members.partials.card', [
                        'member' => $member,
                        'avatarClass' => 'bg-slate-900 text-white',
                    ])
                @empty
                    <p class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3">
                        メインメンバーは未登録です
                    </p>
                @endforelse
            </div>
        </section>

        {{-- サブメンバー --}}
        <section class="mb-12">
            <h2 class="mb-5 text-xl font-bold text-slate-800">
                サブメンバー
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($subMembers as $member)
                    @include('members.partials.card', [
                        'member' => $member,
                        'type' => 'サブメンバー',
                        'avatarClass' => 'bg-slate-100 text-slate-700',
                    ])
                @empty
                    <p class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3">
                        サブメンバーは未登録です
                    </p>
                @endforelse
            </div>
        </section>

        {{-- ゲスト --}}
        <section>
            <h2 class="mb-5 text-xl font-bold text-slate-800">
                ゲスト・参加メンバー
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($guestMembers as $member)
                    @include('members.partials.card', [
                        'member' => $member,
                        'type' => 'ゲスト・参加メンバー',
                        'avatarClass' => 'bg-slate-100 text-slate-700',
                    ])
                @empty
                    <p class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3">
                        ゲスト・参加メンバーは未登録です
                    </p>
                @endforelse
            </div>
        </section>

        {{-- 戻る --}}
        <div class="mt-10 flex justify-end">
            <a href="{{ route('home') }}"
               class="rounded-full border border-slate-300 px-8 py-3 font-bold text-slate-700 transition hover:bg-slate-100">
                TOPへ戻る
            </a>
        </div>

    </div>
</div>
@endsection
