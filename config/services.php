<?php

	return [

		/*
		|--------------------------------------------------------------------------
		| Third Party Services
		|--------------------------------------------------------------------------
		|
		| This file is for storing the credentials for third party services such
		| as Mailgun, Postmark, AWS and more. This file provides the de facto
		| location for this type of information, allowing packages to have
		| a conventional file to locate the various service credentials.
		|
		*/

		'mailgun' => [
			'domain' => env('MAILGUN_DOMAIN'),
			'secret' => env('MAILGUN_SECRET'),
			'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
			'scheme' => 'https',
		],

		'postmark' => [
			'token' => env('POSTMARK_TOKEN'),
		],

		'ses' => [
			'key' => env('AWS_ACCESS_KEY_ID'),
			'secret' => env('AWS_SECRET_ACCESS_KEY'),
			'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
		],

		'openrouter' => [
			'key' => env('OPEN_ROUTER_API_KEY'),
		],

		'fal' => [
			'api_key' => env('FAL_API_KEY', env('FAL_KEY')),
			'platform_url' => env('FAL_PLATFORM_URL', 'https://api.fal.ai/v1'),
			'render_timeout' => (int) env('FAL_TIMEOUT', 180),
		],

		'python' => [
			'executable' => env('PYTHON_EXECUTABLE_PATH', 'python3'),
		]

	];
