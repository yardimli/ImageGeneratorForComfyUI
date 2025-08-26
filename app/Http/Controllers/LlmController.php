<?php

	namespace App\Http\Controllers;

	use App\Models\LlmLog;
	use App\Models\LlmPrompt;
	use Illuminate\Http\Client\Response;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
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
		 * Caches the result for 24 hours to a file in storage/app/temp.
		 *
		 * @return array
		 * @throws \Illuminate\Http\Client\RequestException
		 */
		public function getModels(): array
		{
			$cachePath = storage_path('app/temp');
			$cacheFile = $cachePath . '/openrouter_models.json';
			$cacheDurationInSeconds = 24 * 60 * 60; // 24 hours

			if (File::exists($cacheFile) && (time() - File::lastModified($cacheFile)) < $cacheDurationInSeconds) {
				$cachedContent = File::get($cacheFile);
				return json_decode($cachedContent, true);
			}

			$response = Http::withHeaders([
				'Accept' => 'application/json',
				'HTTP-Referer' => config('app.url'),
				'X-Title' => config('app.name'),
			])->get($this->apiBaseUrl . '/models');

			$response->throw();

			$modelsData = $response->json();

			File::ensureDirectoryExists($cachePath);
			File::put($cacheFile, json_encode($modelsData));

			return $modelsData;
		}

		//  Add method to process models for dropdowns.
		/**
		 * Processes the raw models list from OpenRouter to create a view-friendly array.
		 * It filters models based on positive/negative lists, adds suffixes for image support,
		 * and creates variants for reasoning support.
		 *
		 * @param array $modelsData The raw JSON response from the getModels() method.
		 * @return array A sorted array of models ready for a dropdown.
		 */
		public function processModelsForView(array $modelsData): array
		{
			$processedModels = [];
			// Define the filter lists. The model 'id' will be checked against these.
			$positiveList = ['openai', 'anthropic', 'mistral', 'google', 'deepseek', 'mistral', 'moonshot', 'glm'];
			$negativeList = ['free', '8b', '9b', '3b', '7b', '12b', '22b', '24b', '32b', 'gpt-4 turbo', 'oss', 'tng', 'lite', '1.5', '2.0', 'tiny', 'gemma', 'small', 'nano', ' mini', '-mini', 'nemo', 'chat', 'distill', '3.5', 'dolphin', 'codestral', 'devstral', 'magistral', 'pixtral', 'codex', 'o1-pro', 'o3-pro', 'experimental', 'preview'];

			// Use collect on the 'data' key and sort by name.
			$models = collect($modelsData['data'] ?? [])->sortBy('name');

			foreach ($models as $model) {
				$id = $model['id'];
				$name = $model['name'];
				$idLower = strtolower($id);
				$nameLower = strtolower($name);

				// Negative check: Skip if the ID contains any word from the negative list.
				$isNegativeMatch = collect($negativeList)->contains(fn($word) => str_contains($idLower, $word));
				$isNegativeMatch = $isNegativeMatch || collect($negativeList)->contains(fn($word) => str_contains($nameLower, $word));
				if ($isNegativeMatch) {
					continue;
				}

				// Positive check: Must contain at least one word from the positive list.
				$isPositiveMatch = collect($positiveList)->contains(fn($word) => str_contains($idLower, $word));
				$isPositiveMatch = $isPositiveMatch || collect($positiveList)->contains(fn($word) => str_contains($nameLower, $word));
				if (!$isPositiveMatch) {
					continue;
				}

				// Check for image and reasoning support in the model's metadata.
				$hasImageSupport = in_array('image', $model['architecture']['input_modalities'] ?? []);
				$hasReasoningSupport = in_array('reasoning', $model['supported_parameters'] ?? []);

				if ($hasImageSupport) {
					$name .= ' (i)';
				}

				if ($hasReasoningSupport && stripos($name, 'think') === false) {
					// Create a 'non-thinking' version.
					$processedModels[] = [
						'id' => $id,
						'name' => $name
					];
					// Create a 'thinking' version with a special suffix in the ID.
					$processedModels[] = [
						'id' => $id . '--thinking',
						'name' => $name . ' (thinking)'
					];
				} else {
					$processedModels[] = [
						'id' => $id,
						'name' => $name
					];
				}
			}
			// Sort the final list by name again to correctly order the new variants.
			return collect($processedModels)->sortBy('name')->values()->all();
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
			set_time_limit(300); // 5-minute timeout for long LLM calls.
			if (!$this->apiKey) {
				throw new \Exception('OpenRouter API key is not configured. Please add it to your .env file.');
			}

			//  Handle 'thinking' model variants by checking for the '--thinking' suffix.
			$useReasoning = false;
			if (str_ends_with($modelId, '--thinking')) {
				$modelId = substr($modelId, 0, -10); // Remove '--thinking' to get the real model ID.
				$useReasoning = true;
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

			//  Add the 'reasoning' parameter to the request body if the 'thinking' variant was selected.
			if ($useReasoning) {
				$requestBody['reasoning'] = ['effort' => 'medium'];
			}


			try {
				$response = Http::withToken($this->apiKey)
					->withHeaders([
						'Content-Type' => 'application/json',
						'HTTP-Referer' => config('app.url'),
						'X-Title' => config('app.name'),
					])
					->timeout(240) // 4-minute timeout for long generations
					->post($this->apiBaseUrl . '/chat/completions', $requestBody);

				Log::info('LLM API response body: ' . $response->body());

				if ($response->failed()) {
					$this->logLlmInteraction($prompt, $response->body(), true);
					$response->throw();
				}

				$jsonResponse = $response->json();

				$promptTokens = $jsonResponse['usage']['prompt_tokens'] ?? 0;
				$completionTokens = $jsonResponse['usage']['completion_tokens'] ?? 0;
				$this->logTokenUsage($callReason, $modelId, $promptTokens, $completionTokens);

				if (isset($jsonResponse['choices'][0]['message']['content'])) {
					$llmContent = $jsonResponse['choices'][0]['message']['content'];
					$this->logLlmInteraction($prompt, $llmContent);
					return json_decode($llmContent, true);
				}

				throw new \Exception('Invalid response structure from LLM. ');

			} catch (Throwable $e) {
				$this->logLlmInteraction($prompt, $e->getMessage(), true);
				// Re-throw the exception to be handled by the calling method
				throw new \Exception('LLM API request failed: ' . $e->getMessage(), $e->getCode(), $e);
			}
		}


		/**
		 * Generates a specified number of creative prompts based on a template.
		 *
		 * @param string $promptTemplate The template for generating prompts.
		 * @param int $count The exact number of prompts to generate.
		 * @param string $precision Controls the creativity ('Specific', 'Normal', 'Dreamy', 'Hallucinating').
		 * @param string $originalPrompt The user's core prompt to be inserted into the template.
		 * @param string $modelId The OpenRouter model to use for generation.
		 * @return array An array of generated prompt strings.
		 * @throws \Exception
		 */
		public function generateChatPrompts(string $promptTemplate, int $count, string $precision, string $originalPrompt = '', string $modelId = 'openai/gpt-4o-mini'): array
		{
			set_time_limit(300); // 5-minute timeout for long generations
			// Set temperature based on precision
			$temperature = 1.0;
			switch ($precision) {
				case 'Specific':
					$temperature = 0.5;
					break;
				case 'Normal':
					$temperature = 1.0;
					break;
				case 'Dreamy':
					$temperature = 1.25;
					break;
				case 'Hallucinating':
					$temperature = 1.5;
					break;
			}

			// Handle multi-prompts separated by '::'
			if (strpos($promptTemplate, '::') !== false) {
				return $this->processMultiPrompts($promptTemplate, $count, $originalPrompt, $temperature, $modelId);
			}

			$prompt = str_replace('{prompt}', "\"$originalPrompt\"", $promptTemplate);
			$prompt = $this->normalizeText($prompt);

			return $this->retryQueryLlm($prompt, $count, $temperature, $modelId);
		}

		/**
		 * Generates a text description or modification prompt from an image using a vision model.
		 *
		 * @param string $prompt The text prompt to guide the vision model.
		 * @param string $base64Image The base64-encoded image data.
		 * @param string $mimeType The MIME type of the image (e.g., 'image/jpeg').
		 * @param string $modelId The OpenRouter vision model to use.
		 * @return string The generated text from the vision model.
		 * @throws \Exception
		 */
		public function generatePromptFromImage(string $prompt, string $base64Image, string $mimeType = 'image/jpeg', string $modelId = 'openai/gpt-4o'): string
		{
			set_time_limit(300); // 5-minute timeout for long generations
			if (!$this->apiKey) {
				throw new \Exception('OpenRouter API key is not configured. Please add it to your .env file.');
			}

			$dataUri = "data:" . $mimeType . ";base64," . $base64Image;

			$requestBody = [
				'model' => $modelId,
				'messages' => [
					[
						"role" => "user",
						"content" => [
							["type" => "text", "text" => $prompt],
							["type" => "image_url", "image_url" => ["url" => $dataUri]]
						]
					]
				],
				'max_tokens' => 300,
				'temperature' => 0.5,
			];

			try {
				$response = Http::withToken($this->apiKey)
					->withHeaders([
						'Content-Type' => 'application/json',
						'HTTP-Referer' => config('app.url'),
						'X-Title' => config('app.name'),
					])
					->timeout(240)
					->post($this->apiBaseUrl . '/chat/completions', $requestBody);

				if ($response->failed()) {
					$this->logLlmInteraction($prompt, $response->body(), true);
					$response->throw();
				}

				$jsonResponse = $response->json();

				$promptTokens = $jsonResponse['usage']['prompt_tokens'] ?? 0;
				$completionTokens = $jsonResponse['usage']['completion_tokens'] ?? 0;
				$this->logTokenUsage('Vision Prompt Generation', $modelId, $promptTokens, $completionTokens);

				if (isset($jsonResponse['choices'][0]['message']['content'])) {
					$llmContent = $jsonResponse['choices'][0]['message']['content'];
					$this->logLlmInteraction($prompt, $llmContent);
					return $llmContent;
				}

				throw new \Exception('Invalid response structure from Vision LLM.');
			} catch (Throwable $e) {
				$this->logLlmInteraction($prompt, $e->getMessage(), true);
				throw new \Exception('Vision LLM API request failed: ' . $e->getMessage(), $e->getCode(), $e);
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

		//  Added private helper methods for prompt generation.

		/**
		 * Helper method to process multi-part prompts.
		 *
		 * @param string $chatgptPrompt The full multi-part prompt string.
		 * @param int $batchCount The default number of prompts for each part.
		 * @param string $originalPrompt The user's core prompt.
		 * @param float $temperature The creativity temperature.
		 * @param string $modelId The OpenRouter model ID.
		 * @return array
		 * @throws \Exception
		 */
		private function processMultiPrompts(string $chatgptPrompt, int $batchCount, string $originalPrompt, float $temperature, string $modelId): array
		{
			set_time_limit(300); // 5-minute timeout for long generations
			$promptSplit = explode("::", $chatgptPrompt);
			$prompts = [];
			$explodePrompts = false;
			for ($index = 0; $index < count($promptSplit); $index++) {
				$prompt = preg_replace('/^[\d]+/', '', $promptSplit[$index]);
				$promptCount = $batchCount;
				if ($index != count($promptSplit) - 1) {
					if (preg_match('/^[\d]+/', $promptSplit[$index + 1], $matches)) {
						$explodePrompts = true;
						$promptCount = intval($matches[0]);
					}
				}
				if (trim($prompt) !== '') {
					$prompts[] = [
						'count' => $promptCount,
						'prompt_template' => trim($prompt)
					];
				}
			}

			$results = [];
			foreach ($prompts as $promptData) {
				$prompt = str_replace('{prompt}', "\"$originalPrompt\"", $promptData['prompt_template']);
				$chatgptAnswers = $this->retryQueryLlm($prompt, $promptData['count'], $temperature, $modelId);
				if (empty($results)) {
					$results = $chatgptAnswers;
					continue;
				}

				if ($explodePrompts) {
					$tempResults = [];
					foreach ($results as $result) {
						foreach ($chatgptAnswers as $answer) {
							$separator = (str_ends_with($result, ',') || str_ends_with($result, '.')) ? ' ' : ', ';
							$tempResults[] = $result . $separator . $answer;
						}
					}
					$results = $tempResults;
				} else {
					foreach ($chatgptAnswers as $i => $answer) {
						if (isset($results[$i])) {
							$separator = (str_ends_with($results[$i], ',') || str_ends_with($result, '.')) ? ' ' : ', ';
							$results[$i] .= $separator . $answer;
						}
					}
				}
			}
			return $results;
		}

		/**
		 * Retries calling the LLM until the desired count of prompts is received.
		 *
		 * @param string $prompt The final prompt to send to the LLM.
		 * @param int $count The expected number of answers.
		 * @param float $temperature The creativity temperature.
		 * @param string $modelId The OpenRouter model ID.
		 * @return array
		 * @throws \Exception
		 */
		//  Refactor to use LlmPrompt from the database.
		private function retryQueryLlm(string $prompt, int $count, float $temperature, string $modelId): array
		{
			set_time_limit(300); // 5-minute timeout for long generations
			$max_retries = 4;
			$answers = [];
			for ($i = 0; $i < $max_retries; $i++) {
				try {
					$is_last_retry = ($i == $max_retries - 1 && $max_retries > 1);

					// Fetch the prompt template from the database.
					$llmPrompt = LlmPrompt::where('name', 'prompt.generate.creative')->firstOrFail();

					$system_prompt = str_replace('{count}', $count, $llmPrompt->system_prompt);
					$system_prompt = str_replace(
						['{count}', '{prompt}', '{retry_instruction}'],
						[$count, $prompt, ($is_last_retry ? "\nReturn exactly {$count} answers to my question." : "")],
						$llmPrompt->system_prompt
					);

					$rawResponse = $this->callLlmRaw($system_prompt, $modelId, $temperature);

					$answers = $this->parseResponse($rawResponse);
					if (count($answers) === $count) {
						return $answers;
					}
				} catch (Throwable $e) {
					if ($i === $max_retries - 1) {
						throw $e;
					}
					Log::warning("LLM query failed. Retrying. Error: " . $e->getMessage());
					$temperature = max(0.5, $temperature - 0.3); // Reduce temperature on retry
				}
			}
			throw new \Exception("LLM answers doesn't match batch count. Got " . count($answers) . " answers, expected {$count}.");
		}


		/**
		 * Calls a specified LLM and returns the raw string content, without JSON parsing.
		 *
		 * @param string $systemPrompt
		 * @param string $userPrompt
		 * @param string $modelId
		 * @param float|null $temperature
		 * @return string
		 * @throws \Exception
		 */
		private function callLlmRaw(string $systemPrompt, string $modelId, ?float $temperature = null): string
		{
			set_time_limit(300); // 5-minute timeout for long generations
			if (!$this->apiKey) {
				throw new \Exception('OpenRouter API key is not configured. Please add it to your .env file.');
			}

			//  Handle 'thinking' model variants by checking for the '--thinking' suffix.
			$useReasoning = false;
			if (str_ends_with($modelId, '--thinking')) {
				$modelId = substr($modelId, 0, -10); // Remove '--thinking' to get the real model ID.
				$useReasoning = true;
			}


			$requestBody = [
				'model' => $modelId,
				'messages' => [
					['role' => 'system', 'content' => ''],
					['role' => 'user', 'content' => $systemPrompt]
				],
			];

			if (is_numeric($temperature)) {
				$requestBody['temperature'] = $temperature;
			}

			//  Add the 'reasoning' parameter to the request body if the 'thinking' variant was selected.
			if ($useReasoning) {
				$requestBody['reasoning'] = ['effort' => 'medium'];
			}


			try {
				$response = Http::withToken($this->apiKey)
					->withHeaders([
						'Content-Type' => 'application/json',
						'HTTP-Referer' => config('app.url'),
						'X-Title' => config('app.name'),
					])
					->timeout(240)
					->post($this->apiBaseUrl . '/chat/completions', $requestBody);

				if ($response->failed()) {
					$this->logLlmInteraction($systemPrompt, $response->body(), true);
					$response->throw();
				}

				$jsonResponse = $response->json();

				$promptTokens = $jsonResponse['usage']['prompt_tokens'] ?? 0;
				$completionTokens = $jsonResponse['usage']['completion_tokens'] ?? 0;
				$this->logTokenUsage('Prompt Generation', $modelId, $promptTokens, $completionTokens);

				if (isset($jsonResponse['choices'][0]['message']['content'])) {
					$llmContent = $jsonResponse['choices'][0]['message']['content'];
					$this->logLlmInteraction($systemPrompt, $llmContent);
					return $llmContent;
				}

				throw new \Exception('Invalid response structure from LLM.');
			} catch (Throwable $e) {
				$this->logLlmInteraction($systemPrompt, $e->getMessage(), true);
				throw new \Exception('LLM API request failed: ' . $e->getMessage(), $e->getCode(), $e);
			}
		}

		/**
		 * Parses the raw string response from an LLM to extract a JSON array.
		 *
		 * @param string $response
		 * @return array
		 * @throws \Exception
		 */
		private function parseResponse(string $response): array
		{
			preg_match('/\[.*\]/s', $response, $matches);
			if (empty($matches)) {
				preg_match('/\{.*\}/s', $response, $matches);
			}
			if (empty($matches)) {
				throw new \Exception("No JSON structure found in response");
			}
			$jsonStr = $matches[0];

			$jsonStr = preg_replace('/\}[\s]*\{/', '}, {', $jsonStr);
			$jsonStr = preg_replace('/\][\s]*\[/', '], [', $jsonStr);
			$jsonStr = preg_replace('/\"[\s]*\"/', '", "', $jsonStr);

			$parsed = json_decode($jsonStr, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$parsed = json_decode("[$jsonStr]", true);
			}
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new \Exception("Failed to parse JSON response: " . json_last_error_msg());
			}

			return $this->flattenJsonStructure($parsed);
		}

		/**
		 * Flattens a potentially nested array structure into a single array of strings.
		 *
		 * @param mixed $data
		 * @return array
		 */
		private function flattenJsonStructure($data): array
		{
			if (is_string($data)) {
				return [$data];
			}
			if (is_array($data)) {
				$result = [];
				foreach ($data as $item) {
					if (is_array($item)) {
						$result = array_merge($result, $this->flattenJsonStructure($item));
					} elseif (is_string($item)) {
						$result[] = $item;
					}
				}
				return $result;
			}
			return [];
		}

		/**
		 * Normalizes text by cleaning up newlines and extra spaces.
		 *
		 * @param string $text
		 * @return string
		 */
		private function normalizeText(string $text): string
		{
			$text = preg_replace('/(\.|:|,)[\s]*\n[\s]*/', '$1 ', $text);
			$text = preg_replace('/[\s]*\n[\s]*/', '. ', $text);
			$text = preg_replace('/\s+/', ' ', $text);
			return trim($text);
		}

	}
