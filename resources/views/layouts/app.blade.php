<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'DreamCover'))</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
    <link rel="manifest" href="/images/site.webmanifest">
    <script>
        document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body class="flex min-h-screen flex-col">
<div id="app" class="flex min-h-screen flex-1 flex-col">
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/90">
        <nav class="mx-auto flex min-h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8" aria-label="Main navigation">
            <a href="{{ url('/') }}" class="mr-auto flex items-center gap-2.5 font-bold tracking-tight text-slate-950 dark:text-white">
                <span class="relative flex size-10 rotate-[-3deg] items-center justify-center rounded-xl bg-gradient-to-br from-white via-violet-100 to-violet-300 shadow-[0_7px_14px_-5px_rgba(64,38,138,.8),inset_0_1px_1px_rgba(255,255,255,.9)] ring-1 ring-violet-300/70 dark:from-violet-300 dark:via-violet-500 dark:to-indigo-700"><img src="{{ asset('images/dreamcover-logo-3d.png') }}" onerror="this.src='{{ asset('images/android-chrome-192x192.png') }}'" alt="" class="size-8 object-contain drop-shadow-[0_3px_2px_rgba(24,16,60,.45)]"></span>
                <span>DreamCover</span>
            </a>

            <button id="mobile-menu-button" type="button" class="rounded-xl border border-slate-200 p-2 text-slate-600 lg:hidden dark:border-slate-700 dark:text-slate-200" aria-controls="mobile-menu" aria-expanded="false">
                <span class="sr-only">Open menu</span>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div id="mobile-menu" class="absolute inset-x-4 top-[4.25rem] hidden flex-col gap-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl lg:static lg:flex lg:flex-row lg:items-center lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none dark:border-slate-700 dark:bg-slate-900 lg:dark:bg-transparent">
                @auth
                    <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">Overview</a>
                    <a href="{{ route('gallery.index', ['sort' => 'updated_at']) }}" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">Gallery</a>
                    <div class="relative" data-menu>
                        <button type="button" data-menu-button class="flex w-full items-center justify-between gap-1 rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">Create <span aria-hidden="true">⌄</span></button>
                        <div data-menu-panel class="hidden min-w-56 rounded-xl border border-slate-200 bg-white p-1 shadow-xl lg:absolute lg:left-0 lg:top-full dark:border-slate-700 dark:bg-slate-900">
                            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('prompts.index') }}">Image prompts</a>
                            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('prompt-dictionary.index') }}">Prompt dictionary</a>
                            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('image-edit.index') }}">Image editor</a>
                            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('image-editor-pro.index') }}">Image editor pro</a>
                            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('photoshop.index') }}" target="_blank" rel="noopener">Photoshop <span class="float-right text-xs text-slate-400">↗</span></a>

                            <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('stories.index') }}">Story studio</a>
                        </div>
                    </div>
                    <a href="{{ route('prompts.queue') }}" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                        Queue <span id="navQueueCount" class="ml-1 rounded-full bg-dream-100 px-2 py-0.5 text-xs font-bold text-dream-700 dark:bg-dream-600/20 dark:text-dream-100">{{ \App\Models\Prompt::whereIn('generation_type', ['prompt', 'layerize'])->whereIn('render_status', [0, 1, 3])->count() }}</span>
                    </a>
                    @if(auth()->user()->is_admin)
                        <div class="relative" data-menu>
                            <button type="button" data-menu-button class="flex w-full items-center justify-between gap-1 rounded-lg px-3 py-2 text-sm font-medium text-dream-700 hover:bg-dream-50 dark:text-dream-100 dark:hover:bg-slate-800">Admin <span aria-hidden="true">⌄</span></button>
                            <div data-menu-panel class="hidden min-w-56 rounded-xl border border-slate-200 bg-white p-1 shadow-xl lg:absolute lg:right-0 lg:top-full dark:border-slate-700 dark:bg-slate-900">
                                <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('admin.users.index') }}">Users & stats</a>
                                <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('register') }}">Create user</a>
                                <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('llm-prompts.index') }}">LLM prompts</a>
                                <a class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" href="{{ route('upscale-settings.index') }}">Upscale settings</a>
                            </div>
                        </div>
                    @endif
                @else
                    <a href="{{ url('/#features') }}" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">Features</a>
                    <a href="{{ route('login') }}" class="rounded-xl bg-dream-600 px-4 py-2 text-sm font-semibold text-white hover:bg-dream-700">Sign in</a>
                @endauth
            </div>

            <button id="theme-switcher-btn" type="button" class="rounded-xl border border-slate-200 p-2 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" aria-label="Toggle color theme">
                <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1111.2 3 7 7 0 0021 12.8z"/></svg>
                <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3l1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3l1.42-1.42"/></svg>
            </button>

            @auth
                <div class="relative" data-menu>
                    <button type="button" data-menu-button class="flex size-9 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white dark:bg-dream-600" aria-label="User menu">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</button>
                    <div data-menu-panel class="absolute right-0 top-full hidden min-w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        <div class="px-3 py-2 text-xs text-slate-500">Signed in as<br><strong class="text-slate-800 dark:text-white">{{ Auth::user()->name }}</strong></div>
                        <form action="{{ route('logout') }}" method="POST">@csrf<button class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Sign out</button></form>
                    </div>
                </div>
            @endauth
        </nav>
    </header>

    @if(session()->has('impersonator_id') && auth()->check())
        <div class="bg-amber-300 px-4 py-2 text-center text-sm font-semibold text-slate-950">
            You are viewing DreamCover as {{ auth()->user()->name }}.
            <form class="inline" action="{{ route('admin.impersonation.stop') }}" method="POST">@csrf<button class="ml-2 underline">Return to admin account</button></form>
        </div>
    @endif

    <main class="flex-1 py-8">@yield('content')</main>

    @auth
        <aside id="renderQueuePanel" class="hidden fixed bottom-4 right-4 z-50 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-2xl backdrop-blur-xl transition-all dark:border-slate-700 dark:bg-slate-900/95" aria-label="Global render queue">
            <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <span id="renderQueuePulse" class="size-2.5 rounded-full bg-slate-400"></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-slate-950 dark:text-white">Global render queue</p>
                    <p id="renderQueueSummary" class="truncate text-xs text-slate-500 dark:text-slate-400">Checking queue…</p>
                </div>
                <span id="renderQueueBadge" class="rounded-full bg-dream-100 px-2.5 py-1 text-xs font-bold text-dream-700 dark:bg-dream-600/20 dark:text-dream-100">0</span>
                <button id="renderQueueCollapse" type="button" class="rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" aria-expanded="true" aria-controls="renderQueueBody">
                    <span class="sr-only">Collapse render queue</span>
                    <svg id="renderQueueChevron" class="size-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 15 6-6 6 6"/></svg>
                </button>
            </div>
            <div id="renderQueueBody">
                <div class="grid grid-cols-4 gap-px bg-slate-200 dark:bg-slate-700">
                    <div class="bg-white px-3 py-2 text-center dark:bg-slate-900"><strong id="renderQueuedCount" class="block text-sm text-slate-950 dark:text-white">0</strong><span class="text-[10px] uppercase tracking-wide text-slate-500">Queued</span></div>
                    <div class="bg-white px-3 py-2 text-center dark:bg-slate-900"><strong id="renderProcessingCount" class="block text-sm text-slate-950 dark:text-white">0</strong><span class="text-[10px] uppercase tracking-wide text-slate-500">Active</span></div>
                    <div class="bg-white px-3 py-2 text-center dark:bg-slate-900"><strong id="renderRetryCount" class="block text-sm text-slate-950 dark:text-white">0</strong><span class="text-[10px] uppercase tracking-wide text-slate-500">Retry</span></div>
                    <div class="bg-white px-3 py-2 text-center dark:bg-slate-900"><strong id="renderFailedCount" class="block text-sm text-slate-950 dark:text-white">0</strong><span class="text-[10px] uppercase tracking-wide text-slate-500">Failed</span></div>
                </div>
                <div id="renderQueueJobs" class="max-h-60 space-y-2 overflow-y-auto p-3">
                    <p class="py-4 text-center text-sm text-slate-500">Loading jobs…</p>
                </div>
                <div class="border-t border-slate-200 px-4 py-2 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    Shared across all users. Keep any DreamCover page open while rendering.
                </div>
            </div>
        </aside>
    @endauth

    <footer class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8 dark:text-slate-400">
            <div class="flex items-center gap-2"><img src="{{ asset('images/favicon-32x32.png') }}" alt="" class="size-6"><span>DreamCover — turn ideas into images and illustrated stories.</span></div>
            <div class="flex gap-5"><a class="hover:text-dream-600" href="{{ url('/') }}">Overview</a>@auth<a class="hover:text-dream-600" href="{{ route('gallery.index') }}">Gallery</a><a class="hover:text-dream-600" href="{{ route('stories.index') }}">Stories</a>@endauth</div>
        </div>
    </footer>
</div>

<script>
    document.getElementById('theme-switcher-btn')?.addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    });
    const mobileButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    mobileButton?.addEventListener('click', () => {
        const open = mobileMenu.classList.toggle('hidden');
        mobileMenu.classList.toggle('flex', !open);
        mobileButton.setAttribute('aria-expanded', String(!open));
    });
    document.querySelectorAll('[data-menu-button]').forEach((button) => button.addEventListener('click', (event) => {
        event.stopPropagation();
        const panel = button.parentElement.querySelector('[data-menu-panel]');
        document.querySelectorAll('[data-menu-panel]').forEach((item) => { if (item !== panel) item.classList.add('hidden'); });
        panel.classList.toggle('hidden');
    }));
    document.addEventListener('click', () => document.querySelectorAll('[data-menu-panel]').forEach((item) => item.classList.add('hidden')));
</script>
@auth
    <script>
        window.dreamCoverRenderQueue = {
            statusUrl: @json(route('render-queue.status')),
            processUrl: @json(route('render-queue.process')),
            cancelUrl: @json(url('/render-queue')),
            csrfToken: @json(csrf_token()),
        };
    </script>
    <script src="{{ asset('js/render-queue.js') }}?v={{ filemtime(public_path('js/render-queue.js')) }}"></script>
@endauth
@yield('scripts')
</body>
</html>
