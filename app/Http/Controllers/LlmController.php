<?php

	namespace App\Http\Controllers;

	use App\Models\LlmLog;
	use Illuminate\Http\Client\Response;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Throwable;

	/**
	 * Handles all low-level communication with the OpenRouter LLM API.
	 * Converted from node-llm-api.js.
	 */
	class LlmController extends Controller
	{
		protected ?string $apiKey;
		protected string $apiBaseUrl = 'https://openrouter.ai/api/v1';

		/**
		 * Set up the controller, fetching the API key from config.
		 *
		 * IMPORTANT: Add your OpenRouter API key to your .env file:
		 * OPENROUTER_API_KEY=your_key_here
		 *
		 * And reference it in config/services.php:
		 * 'openrouter' => [
		 *     'key' => env('OPENROUTER_API_KEY'),
		 * ],
		 */
		public function __construct()
		{
			$this->apiKey = config('services.openrouter.key');
		}

		/**
		 * Fetches the list of available models from the OpenRouter API.
		 *
		 * @return array
		 * @throws \Illuminate\Http\Client\RequestException
		 */
		public function getModels(): array
		{
			$response = Http::withHeaders([
				'Accept' => 'application/json',
				'HTTP-Referer' => config('app.url'),
				'X-Title' => config('app.name'),
			])->get($this->apiBaseUrl . '/models');

			$response->throw(); // Throw an exception if the request failed

			return $response->json();
		}

		/**
		 * Calls a specified LLM synchronously, waiting for the full response.
		 *
		 * @param string $prompt
		 * @param string $modelId
		 * @param string $callReason
		 * @param float|null $temperature
		 * @param string|null $responseFormat
		 * @return array
		 * @throws \Exception
		 */
		public function callLlmSync(string $prompt, string $modelId, string $callReason = 'Unknown', ?float $temperature = null, ?string $responseFormat = 'json_object'): array
		{
			if (!$this->apiKey) {
				throw new \Exception('OpenRouter API key is not configured. Please add it to your .env file.');
			}

			$requestBody = [
				'model' => $modelId,
				'messages' => [['role' => 'user', 'content' => $prompt]],
			];

			if ($responseFormat) {
				$requestBody['response_format'] = ['type' => $responseFormat];
			}

			if (is_numeric($temperature)) {
				$requestBody['temperature'] = $temperature;
			}

			try {
				$response = Http::withToken($this->apiKey)
					->withHeaders([
						'Content-Type' => 'application/json',
						'HTTP-Referer' => config('app.url'),
						'X-Title' => config('app.name'),
					])
					->timeout(180) // 3-minute timeout for long generations
					->post($this->apiBaseUrl . '/chat/completions', $requestBody);

				if ($response->failed()) {
					$this->logLlmInteraction($prompt, $response->body(), true);
					$response->throw();
				}

				$jsonResponse = $response->json();

				// Log token usage
				$promptTokens = $jsonResponse['usage']['prompt_tokens'] ?? 0;
				$completionTokens = $jsonResponse['usage']['completion_tokens'] ?? 0;
				$this->logTokenUsage($callReason, $modelId, $promptTokens, $completionTokens);

				if (isset($jsonResponse['choices'][0]['message']['content'])) {
					$llmContent = $jsonResponse['choices'][0]['message']['content'];
					$this->logLlmInteraction($prompt, $llmContent);
					return json_decode($llmContent, true);
				}

				throw new \Exception('Invalid response structure from LLM.');

			} catch (Throwable $e) {
				$this->logLlmInteraction($prompt, $e->getMessage(), true);
				// Re-throw the exception to be handled by the calling method
				throw new \Exception('LLM API request failed: ' . $e->getMessage(), $e->getCode(), $e);
			}
		}

		/**
		 * Logs an interaction with the LLM to a file for debugging purposes.
		 *
		 * @param string $prompt
		 * @param string $response
		 * @param bool $isError
		 */
		private function logLlmInteraction(string $prompt, string $response, bool $isError = false): void
		{
			$logHeader = $isError ? '--- LLM ERROR ---' : '--- LLM INTERACTION ---';
			$logEntry = "{$logHeader}\nTimestamp: " . now()->toIso8601String() . "\n---\nPROMPT SENT\n---\n{$prompt}\n---\nRESPONSE RECEIVED\n---\n{$response}\n--- END ---\n\n";

			Log::channel('daily')->info($logEntry);
		}

		/**
		 * Logs token usage to the database.
		 *
		 * @param string $callReason
		 * @param string $modelId
		 * @param int $promptTokens
		 * @param int $completionTokens
		 */
		private function logTokenUsage(string $callReason, string $modelId, int $promptTokens, int $completionTokens): void
		{
			try {
				LlmLog::create([
					'user_id' => auth()->id(),
					'reason' => $callReason,
					'model_id' => $modelId,
					'prompt_tokens' => $promptTokens,
					'completion_tokens' => $completionTokens,
					'timestamp' => now(),
				]);
			} catch (Throwable $e) {
				Log::error('Failed to log LLM token usage to database: ' . $e->getMessage());
			}
		}
	}
