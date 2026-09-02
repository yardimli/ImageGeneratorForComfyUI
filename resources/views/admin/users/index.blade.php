@extends('layouts.app')

@section('title', 'Users — DreamCover')

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    @if(session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="mb-2 text-sm font-bold uppercase tracking-[.18em] text-dream-600">Administration</p><h1 class="text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Users and activity</h1><p class="mt-2 text-slate-500 dark:text-slate-400">Understand how every workspace is being used or step in to support an account.</p></div>
        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-xl bg-dream-600 px-5 py-3 text-sm font-semibold text-white shadow-glow hover:bg-dream-700">Create a user</a>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Users', $totals['users'], 'bg-dream-100 text-dream-700'], ['Admins', $totals['admins'], 'bg-amber-100 text-amber-700'], ['Generated images', $totals['images'], 'bg-cyan-100 text-cyan-700'], ['Stories', $totals['stories'], 'bg-emerald-100 text-emerald-700']] as [$label, $value, $tone])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><span class="rounded-lg px-2.5 py-1 text-xs font-bold {{ $tone }}">{{ $label }}</span><div class="mt-4 text-3xl font-bold">{{ number_format($value) }}</div></div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[840px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/70 dark:text-slate-400"><tr><th class="px-5 py-4">User</th><th class="px-4 py-4">Role</th><th class="px-4 py-4 text-right">Prompts</th><th class="px-4 py-4 text-right">Images</th><th class="px-4 py-4 text-right">Stories</th><th class="px-4 py-4 text-right">Dictionary</th><th class="px-5 py-4">Last generation</th><th class="px-5 py-4"></th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($users as $user)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="flex size-9 items-center justify-center rounded-full bg-dream-100 font-bold text-dream-700 dark:bg-dream-600/20 dark:text-dream-100">{{ strtoupper(substr($user->name, 0, 1)) }}</span><div><div class="font-semibold text-slate-950 dark:text-white">{{ $user->name }}</div><div class="text-xs text-slate-500">{{ $user->email }} · #{{ $user->id }}</div></div></div></td>
                            <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $user->is_admin ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $user->is_admin ? 'Admin' : 'Member' }}</span></td>
                            <td class="px-4 py-4 text-right tabular-nums">{{ number_format($user->prompts_count) }}</td><td class="px-4 py-4 text-right tabular-nums">{{ number_format($user->images_count) }}</td><td class="px-4 py-4 text-right tabular-nums">{{ number_format($user->stories_count) }}</td><td class="px-4 py-4 text-right tabular-nums">{{ number_format($user->dictionary_entries_count) }}</td>
                            <td class="px-5 py-4 text-xs text-slate-500">{{ $user->last_generation_at ? \Illuminate\Support\Carbon::parse($user->last_generation_at)->diffForHumans() : 'No generations yet' }}</td>
                            <td class="px-5 py-4">@unless($user->is(auth()->user()))<form action="{{ route('admin.users.impersonate', $user) }}" method="POST">@csrf<button class="whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:border-dream-500 hover:text-dream-600 dark:border-slate-700">Login as user</button></form>@endunless</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $users->links() }}</div>
    </div>
</div>
@endsection
