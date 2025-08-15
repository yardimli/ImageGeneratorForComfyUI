<?php

	namespace App\Http\Controllers;

	use App\Http\Controllers\LlmController;
	use App\Models\Story;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;

	class QuizController extends Controller
	{
		/**
		 * Show the quiz management page for a story.
		 *
		 * @param \App\Models\Story $story
		 * @param \App\Http\Controllers\LlmController $llmController
		 * @return \Illuminate\View\View
		 */
		public function quiz(Story $story, LlmController $llmController)
		{
			$story->load(['pages', 'quiz' => function ($query) {
				$query->orderBy('id', 'asc');
			}]);

			// Prepare the prompt text
			$storyText = $story->pages->pluck('story_text')->implode("\n\n");

			$initialUserRequest = "Create 5 multiple-choice quiz questions for the story above. Provide 4 answers for each question. Mark the correct answer with an asterisk (*). Explain the questions in a manner that is understandable for the story's level.";
			$promptText = "Story Title: {$story->title}\nLevel: {$story->level}\n\n---\n\n{$storyText}\n\n---\n\n{$initialUserRequest}";

			// Fetch models for the AI generator
			try {
				$modelsResponse = $llmController->getModels();
				$models = collect($modelsResponse['data'] ?? [])->sortBy('name')->all();
			} catch (\Exception $e) {
				Log::error('Failed to fetch LLM models for Story Quiz: ' . $e->getMessage());
				$models = [];
				session()->flash('error', 'Could not fetch AI models for the quiz generator.');
			}

			return view('story.quiz', compact('story', 'promptText', 'models'));
		}

		/**
		 * Update the quiz questions for a story.
		 *
		 * @param \Illuminate\Http\Request $request
		 * @param \App\Models\Story $story
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function updateQuiz(Request $request, Story $story)
		{
			$validated = $request->validate([
				'quiz' => 'nullable|array',
				'quiz.*.question' => 'required_with:quiz.*.answers|string',
				'quiz.*.answers' => 'required_with:quiz.*.question|string',
			]);

			DB::transaction(function () use ($story, $validated) {
				$story->quiz()->delete();

				if (isset($validated['quiz'])) {
					foreach ($validated['quiz'] as $entry) {
						// Ensure we don't save empty rows that might pass validation
						if (!empty($entry['question']) && !empty($entry['answers'])) {
							$story->quiz()->create([
								'question' => $entry['question'],
								'answers' => $entry['answers'],
							]);
						}
					}
				}
			});

			return redirect()->route('stories.quiz', $story)->with('success', 'Quiz updated successfully!');
		}

		/**
		 * Generate quiz questions for a story using AI.
		 *
		 * @param \Illuminate\Http\Request $request
		 * @param \App\Models\Story $story
		 * @param \App\Http\Controllers\LlmController $llmController
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateQuiz(Request $request, Story $story, LlmController $llmController)
		{
			$validated = $request->validate([
				'prompt' => 'required|string',
				'model' => 'required|string',
			]);

			try {
				$fullPrompt = $this->buildQuizPrompt($validated['prompt']);
				$response = $llmController->callLlmSync(
					$fullPrompt,
					$validated['model'],
					'AI Story Quiz Generation',
					0.7,
					'json_object'
				);

				$quizEntries = $response['quiz'] ?? null;

				if (!is_array($quizEntries)) {
					Log::error('AI Quiz Generation failed to return a valid array.', ['response' => $response]);
					return response()->json(['success' => false, 'message' => 'The AI returned data in an unexpected format. Please try again.'], 422);
				}

				return response()->json([
					'success' => true,
					'quiz' => $quizEntries
				]);
			} catch (\Exception $e) {
				Log::error('AI Quiz Generation Failed: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while generating the quiz. Please try again.'], 500);
			}
		}

		/**
		 * Builds the prompt for the LLM to generate a story quiz.
		 *
		 * @param string $userPrompt
		 * @return string
		 */
		private function buildQuizPrompt(string $userPrompt): string
		{
			$jsonStructure = <<<'JSON'
{
  "quiz": [
    {
      "question": "The question about the story.",
      "answers": "a) Answer one\nb) Answer two\nc) The correct answer*\nd) Answer four"
    }
  ]
}
JSON;

			return <<<PROMPT
You are an expert teacher. Based on the following story text and user request, create a list of multiple-choice quiz questions.
For each entry, provide the question and a list of 4 answers as a single string with newlines.
Mark the correct answer by adding an asterisk (*) at the end of the line.

---
{$userPrompt}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{$jsonStructure}

Now, generate the quiz based on the provided text and user request.
PROMPT;
		}
	}
