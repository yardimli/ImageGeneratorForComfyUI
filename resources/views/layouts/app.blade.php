<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<!-- CSRF Token -->
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>{{ config('app.name', 'Laravel') }}</title>
	
	<!-- Fonts -->
	<link rel="dns-prefetch" href="//fonts.bunny.net">
	<link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
	
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<script>
		// Set theme on page load to prevent flickering
		(function () {
			const theme = localStorage.getItem('theme') || 'light'; // Default to light mode
			document.documentElement.setAttribute('data-bs-theme', theme);
		})();
		
		document.addEventListener('DOMContentLoaded', () => {
			const themeSwitcher = document.getElementById('theme-switcher-btn');
			const themeIcon = document.getElementById('theme-icon');
			
			// SVG icons for themes
			const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sun-fill" viewBox="0 0 16 16"><path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707z"/></svg>`;
			const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-fill" viewBox="0 0 16 16"><path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/></svg>`;
			
			const getPreferredTheme = () => {
				return localStorage.getItem('theme') || 'light';
			};
			
			const setTheme = (theme) => {
				document.documentElement.setAttribute('data-bs-theme', theme);
				localStorage.setItem('theme', theme);
				updateIcon(theme);
			};
			
			const updateIcon = (theme) => {
				if (themeIcon) {
					themeIcon.innerHTML = theme === 'dark' ? sunIcon : moonIcon;
				}
			};
			
			if (themeSwitcher) {
				themeSwitcher.addEventListener('click', () => {
					const currentTheme = document.documentElement.getAttribute('data-bs-theme');
					const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
					setTheme(newTheme);
				});
			}
			
			// Set initial icon on page load
			updateIcon(getPreferredTheme());
		});
	</script>
	@yield('styles')
	
	<!-- Scripts -->
	@vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
<div id="app">
	<nav class="navbar navbar-expand-md bg-body-tertiary shadow-sm">
		<div class="container">
			<a class="navbar-brand" href="{{ url('/') }}">
				{{ config('app.name', 'Laravel') }}
			</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
			        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
				<span class="navbar-toggler-icon"></span>
			</button>
			
			<div class="collapse navbar-collapse" id="navbarSupportedContent">
				<!-- Left Side Of Navbar -->
				<ul class="navbar-nav me-auto">
					<li class="nav-item">
						<a class="nav-link" href="{{ route('home') }}">Home</a>
					</li>
					<li class="nav-item">
						<a class="nav-link"
						   href="{{ route('gallery.index', ['date' => $date ?? '', 'sort' => $sort ?? 'updated_at', 'types' => $selectedTypes ?? ['all']]) }}">Gallery</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="{{ route('prompts.index') }}">Prompts</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="{{ route('image-mix.index') }}">Image Mix</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="{{ route('stories.index') }}">Story</a>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="kontextDropdown" role="button"
						   data-bs-toggle="dropdown" aria-expanded="false">
							Kontext
						</a>
						<ul class="dropdown-menu" aria-labelledby="kontextDropdown">
							<li><a class="dropdown-item" href="{{ route('album-covers.index') }}">Kontext (remote API)</a></li>
							<li><a class="dropdown-item" href="{{ route('kontext-basic.index') }}">Kontext Basic</a></li>
							<li><a class="dropdown-item" href="{{ route('kontext-lora.index') }}">Kontext Lora</a></li>
						</ul>
					</li>
					
					<li class="nav-item">
						<a class="nav-link" href="{{ route('prompts.queue') }}">
							Queue Management <span class="badge bg-primary"
							                       id="navQueueCount">{{ \App\Models\Prompt::where('user_id', auth()->id())->whereIn('render_status', ['queued', 'pending', null])->count() }}</span>
						</a>
					</li>
				</ul>
				
				<!-- Right Side Of Navbar -->
				<ul class="navbar-nav ms-auto">
					<li class="nav-item me-2">
						<button class="btn btn-outline-secondary" id="theme-switcher-btn" style="width: 40px;">
							<span id="theme-icon"></span>
						</button>
					</li>
					<!-- Authentication Links -->
					@guest
						@if (Route::has('login'))
							<li class="nav-item">
								<a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
							</li>
						@endif
						
						@if (Route::has('register'))
							<li class="nav-item">
								<a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
							</li>
						@endif
					@else
						<li class="nav-item dropdown">
							<a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
							   aria-haspopup="true" aria-expanded="false" v-pre>
								{{ Auth::user()->name }}
							</a>
							
							<div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
								<a class="dropdown-item" href="{{ route('logout') }}"
								   onclick="event.preventDefault();
                                             document.getElementById('logout-form').submit();">
									{{ __('Logout') }}
								</a>
								
								<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
									@csrf
								</form>
							</div>
						</li>
					@endguest
				</ul>
			</div>
		</div>
	</nav>
	<main class="py-4">
		@yield('content')
	</main>
</div>

@yield('scripts')
</body>
</html>
