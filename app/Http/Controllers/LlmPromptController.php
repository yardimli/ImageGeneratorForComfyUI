<?php

	namespace App\Http\Controllers;

	use App\Models\LlmPrompt;
	use Illuminate\Http\Request;

	/**
	 * Manages the LLM prompt templates stored in the database.
	 */
	class LlmPromptController extends Controller
	{
		/**
		 * Display a listing of all LLM prompts.
		 *
		 * @return \Illuminate\View\View
		 */
		public function index()
		{
			$prompts = LlmPrompt::orderBy('name')->get();
			return view('llm-prompts.index', compact('prompts'));
		}

		/**
		 * Show the form for editing the specified LLM prompt.
		 *
		 * @param \App\Models\LlmPrompt $prompt
		 * @return \Illuminate\View\View
		 */
		public function edit(LlmPrompt $prompt)
		{
			return view('llm-prompts.edit', compact('prompt'));
		}

		/**
		 * Update the specified LLM prompt in storage.
		 *
		 * @param \Illuminate\Http\Request $request
		 * @param \App\Models\LlmPrompt $prompt
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function update(Request $request, LlmPrompt $prompt)
		{
			$validated = $request->validate([
				'label' => 'required|string|max:255',
				'description' => 'nullable|string',
				'system_prompt' => 'nullable|string',
				'user_prompt' => 'nullable|string',
				'options' => 'nullable|string', // Validation still expects a string from the textarea.
			]);

			//  Decode the JSON string before updating the model.
			if (!empty($validated['options'])) {
				// Decode the JSON string into a PHP associative array.
				$decodedOptions = json_decode($validated['options'], true);

				// Check if the JSON was valid. If not, return with an error.
				if (json_last_error() !== JSON_ERROR_NONE) {
					return back()->withErrors(['options' => 'The options field must contain valid JSON.'])->withInput();
				}

				// Replace the JSON string in the validated data with the PHP array.
				$validated['options'] = $decodedOptions;
			} else {
				// Ensure that if the field is empty, we store null.
				$validated['options'] = null;
			}
			

			$prompt->update($validated);

			return redirect()->route('llm-prompts.edit', $prompt)->with('success', 'Prompt updated successfully!');
		}
	}
