<?php

	// app/Services/ChatGPTService.php
	namespace App\Services;

	use Exception;

	class ChatGPTService
	{
		private $temperature;
		private $max_retries = 4;
		private $originalPrompt = '';  // Add this line

		public function __construct()
		{
		}

		public function log_to_file($data)
		{
			$filename = 'log.txt';
			$fp = fopen($filename, 'a');
			fwrite($fp, $data);
			fclose($fp);
		}

		private function processMultiPrompts($chatgptPrompt, $batchCount, $originalPrompt = '')
		{
			$promptSplit = explode("::", $chatgptPrompt);
			$prompts = [];
			$explodePrompts = false;

			for ($index = 0; $index < count($promptSplit); $index++) {
				// Remove leading numbers from prompt
				$prompt = preg_replace('/^[\d]+/', '', $promptSplit[$index]);
				$promptCount = $batchCount;

				// Check if next prompt starts with a number (batch count)
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
				$chatgptAnswers = $this->retryQueryChatGPT($prompt, $promptData['count']);

				if (empty($results)) {
					$results = $chatgptAnswers;
					continue;
				}

				if ($explodePrompts) {
					$tempResults = [];
					foreach ($results as $result) {
						foreach ($chatgptAnswers as $answer) {
							$separator = (substr($result, -1) === ',' || substr($result, -1) === '.') ? ' ' : ', ';
							$tempResults[] = $result . $separator . $answer;
						}
					}
					$results = $tempResults;
				} else {
					foreach ($chatgptAnswers as $i => $answer) {
						if (isset($results[$i])) {
							$separator = (substr($results[$i], -1) === ',' || substr($results[$i], -1) === '.') ? ' ' : ', ';
							$results[$i] .= $separator . $answer;
						}
					}
				}
			}

			return $results;
		}

		public function generatePrompts($prompt, $count, $precision, $originalPrompt = '')
		{
			$this->originalPrompt = $originalPrompt;

			// Set temperature based on precision
			switch ($precision) {
				case 'Specific':
					$this->temperature = 0.5;
					break;
				case 'Normal':
					$this->temperature = 1.0;
					break;
				case 'Dreamy':
					$this->temperature = 1.25;
					break;
				case 'Hallucinating':
					$this->temperature = 1.5;
					break;
			}

			if (strpos($prompt, '::') !== false) {
				return $this->processMultiPrompts($prompt, $count, $originalPrompt);
			}

			// If appendToPrompt is true and we have an original prompt,
			// we'll prepend it to each generated prompt later
			$prompt = str_replace('{prompt}', "\"$originalPrompt\"", $prompt);
			$prompt = $this->normalizeText($prompt);

			$results = $this->retryQueryChatGPT($prompt, $count);

			return $results;
		}

		private function retryQueryChatGPT($prompt, $count)
		{
			$answers = [];
			$current_temperature = $this->temperature;

			for ($i = 0; $i < $this->max_retries; $i++) {
				try {
					$is_last_retry = ($i == $this->max_retries - 1 && $this->max_retries > 1);

					$messages = [
						[
							'role' => 'system',
							'content' => "Act like you are a terminal and always format your response as json. Always return exactly {$count} answers per question."
						],
						[
							'role' => 'user',
							'content' => "I want you to act as a prompt generator. Compose each answer as a visual sentence. " .
								"Do not write explanations on replies. Format the answers as javascript json arrays with a " .
								"single string per answer. Return exactly {$count} to my question. Answer the questions exactly. " .
								"Answer the following question:\n{$prompt}" .
								($is_last_retry ? "\nReturn exactly {$count} answers to my question." : "")
						]
					];

					$response = $this->queryChatGPT($messages); //, $current_temperature);
					$answers = $this->parseResponse($response);

					if (count($answers) === $count) {
						return $answers;
					}

				} catch (Exception $e) {
					if ($i === $this->max_retries - 1) {
						throw $e;
					}
					error_log("ChatGPT query failed. Retrying. Error: " . $e->getMessage());
					$current_temperature = max(0.5, $current_temperature - 0.3);
				}
			}

			throw new Exception("ChatGPT answers doesn't match batch count. Got " . count($answers) . " answers, expected {$count}.");
		}

		private function queryChatGPT($messages)
		{
			session_write_close();

			$llm_base_url = 'https://api.openai.com/v1/chat/completions';

			foreach ($messages as &$message) {
				if (isset($message['content'])) {
					$message['content'] = preg_replace('/\{prompt\}/', $this->originalPrompt, $message['content']);
				}
			}

			$data = array(
				'model' =>  env('OPEN_AI_MODEL'),
				'messages' => $messages,
				'max_tokens' => 1024,
				'temperature' => $this->temperature,
			);

			$this->log_to_file("\n\n Request Data: \n\n");
			$this->log_to_file(json_encode($messages));


			$post_json = json_encode($data);

			if (json_last_error() !== JSON_ERROR_NONE) {
				echo 'JSON Encoding Error: ' . json_last_error_msg();
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $llm_base_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);

			$headers = array();
			$headers[] = 'Content-Type: application/json';
			$headers[] = "Authorization: Bearer " . env('OPEN_AI_API_KEY');
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$response = curl_exec($ch);
			if (curl_errno($ch)) {
				echo 'CURL Error: ' . curl_error($ch);
				var_dump($response);
			}
			curl_close($ch);
			session_start();

			$this->log_to_file("\n\n Response Data: \n\n");
			$this->log_to_file($response);

			return $response;
		}

		private function parseResponse($response)
		{
			$data = json_decode($response, true);

			if (!isset($data['choices'][0]['message']['content'])) {
				throw new Exception("Invalid response format from ChatGPT");
			}

			$content = $data['choices'][0]['message']['content'];

			// Try to extract JSON from the response
			preg_match('/\[.*\]/s', $content, $matches);
			if (empty($matches)) {
				preg_match('/\{.*\}/s', $content, $matches);
			}

			if (empty($matches)) {
				throw new Exception("No JSON structure found in response");
			}

			$jsonStr = $matches[0];

			// Clean up common formatting issues
			$jsonStr = preg_replace('/\}[\s]*\{/', '}, {', $jsonStr);
			$jsonStr = preg_replace('/\][\s]*\[/', '], [', $jsonStr);
			$jsonStr = preg_replace('/\"[\s]*\"/', '", "', $jsonStr);

			// Try to parse the JSON
			$parsed = json_decode($jsonStr, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				// If parsing failed, try wrapping in array
				$parsed = json_decode("[$jsonStr]", true);
			}

			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new Exception("Failed to parse JSON response: " . json_last_error_msg());
			}

			// Flatten the response structure
			return $this->flattenJsonStructure($parsed);
		}

		private function flattenJsonStructure($data)
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

		private function normalizeText($text)
		{
			// Replace newlines after punctuation with spaces
			$text = preg_replace('/(\.|:|,)[\s]*\n[\s]*/', '$1 ', $text);
			// Replace other newlines with periods
			$text = preg_replace('/[\s]*\n[\s]*/', '. ', $text);
			// Normalize spaces
			$text = preg_replace('/\s+/', ' ', $text);
			return trim($text);
		}


	}
