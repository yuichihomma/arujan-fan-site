@extends('layouts.admin')

@section('content')
    <div class="flex min-h-[calc(100vh-3rem)] items-center justify-center">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <p class="mb-2 text-sm font-semibold text-green-600">Admin</p>
            <h1 class="mb-6 text-2xl font-bold text-gray-900">管理者ログイン</h1>

            <form action="{{ route('admin.login.submit') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-gray-700">メールアドレス</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-base outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                    >
                    @error('email')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-gray-700">パスワード</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-base outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                    >
                    @error('password')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3">
                    <input
                        type="checkbox"
                        name="remember"
                        id="remember"
                        class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                    >
                    <label for="remember" class="text-sm text-gray-600">ログイン状態を保持する</label>
                </div>

                <button
                    type="submit"
                    class="min-h-12 w-full rounded-lg bg-green-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-200 focus:ring-offset-2"
                >
                    ログイン
                </button>
            </form>
        </div>
    </div>
@endsection
