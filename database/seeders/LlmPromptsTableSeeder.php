<?php

	namespace Database\Seeders;

	use Illuminate\Database\Seeder;
	use Illuminate\Support\Facades\DB;

	class LlmPromptsTableSeeder extends Seeder
	{
		/**
		 * Run the database seeds.
		 */
		public function run(): void
		{
			DB::table('llm_prompts')->insert([
				[
					'name' => 'prompt.generate.creative',
					'label' => 'Creative Prompt Generation',
					'description' => 'Generates a batch of creative image prompts based on a user\'s input and a template.',
					'system_prompt' => <<<PROMPT
Act like you are a terminal. Always format your response as a single valid JSON array of strings. Always return exactly {count} answers per question. Do not include any text, markdown, or explanation outside of the JSON array itself.
PROMPT,
					'user_prompt' => <<<PROMPT
I want you to act as a prompt generator. Compose each answer as a visual sentence. Do not write explanations on replies. Format the answers as a javascript json array with a single string per answer. Return exactly {count} to my question. Answer the questions exactly. Answer the following question:
{prompt}{retry_instruction}
PROMPT,
					'placeholders' => json_encode(['{count}', '{prompt}', '{retry_instruction}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'prompt_dictionary.entry.image_prompt',
					'label' => 'Prompt Dictionary Image Prompt Generation',
					'description' => 'Generates an image prompt for a prompt dictionary entry.',
					'system_prompt' => <<<PROMPT
You are an expert at writing image generation prompts for AI art models like DALL-E 3 or Midjourney.
Your task is to create a single, concise, and descriptive image prompt for a dictionary {assetType}.

**Instructions:**
- The prompt should be a single paragraph of comma-separated descriptive phrases.
- Focus on visual details: appearance, key features, mood, and lighting.
- Provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
- The JSON object must follow this exact structure:
{
  "prompt": "A detailed, comma-separated list of visual descriptors for the image."
}

Now, generate the image prompt for the provided context in the specified JSON format.
PROMPT,
					'user_prompt' => <<<PROMPT
**Context:**
1.  **{assetType} Description:**
    "{assetDescription}"

2.  **User Guidance:**
    {userInstructions}
PROMPT,
					'placeholders' => json_encode(['{assetType}', '{assetDescription}', '{userInstructions}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'prompt_dictionary.entry.rewrite',
					'label' => 'Prompt Dictionary Entry Rewrite',
					'description' => 'Rewrites the description for a prompt dictionary entry.',
					'system_prompt' => <<<PROMPT
You are an expert story editor. Your task is to rewrite a description for a story asset based on a specific instruction.
Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{
  "rewritten_text": "The rewritten text goes here."
}

Now, rewrite the text based on the instruction.
PROMPT,
					'user_prompt' => <<<PROMPT
Instruction: "{instruction}"

Original Text:
"{text}"
PROMPT,
					'placeholders' => json_encode(['{instruction}', '{text}']),
					'options' => json_encode([
						'entry' => [
							'detailed' => "Rewrite the following description to be more visually detailed. Focus on specific features and its overall presence. Also remove any instructions or notes from the original text.",
							'simplify' => "Simplify the following description. Use clearer, more concise language suitable for a younger audience or for a quick introduction. Also remove any instructions or notes from the original text.",
							'poetic' => "Rewrite the description in a more poetic and evocative style. Use figurative language and sensory details to create a stronger mood. Also remove any instructions or notes from the original text.",
							'technical' => "Rewrite the description in a more technical style, focusing on precise terminology and objective details.",
							'grammar' => 'Correct any grammatical errors, improve the sentence structure, and enhance the clarity of the following text. Act as a professional editor. Also remove any instructions or notes from the original text.',
						],
					]),
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.asset.image_prompt',
					'label' => 'Story Asset Image Prompt Generation',
					'description' => 'Generates an image prompt for a story character or place.',
					'system_prompt' => <<<PROMPT
You are an expert at writing image generation prompts for AI art models like DALL-E 3 or Midjourney.
Your task is to create a single, concise, and descriptive image prompt for a story {assetType}.

**Instructions:**
- The prompt should be a single paragraph of comma-separated descriptive phrases.
- Focus on visual details: appearance, key features, mood, and lighting.
- Provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
- The JSON object must follow this exact structure:
{
  "prompt": "A detailed, comma-separated list of visual descriptors for the image."
}

Now, generate the image prompt for the provided context in the specified JSON format.
PROMPT,
					'user_prompt' => <<<PROMPT
**Context:**
1.  **{assetType} Description:**
    "{assetDescription}"

2.  **User Guidance:**
    {userInstructions}
PROMPT,
					'placeholders' => json_encode(['{assetType}', '{assetDescription}', '{userInstructions}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.asset.rewrite',
					'label' => 'Story Asset Description Rewrite',
					'description' => 'Rewrites the description for a story character or place.',
					'system_prompt' => <<<PROMPT
You are an expert story editor. Your task is to rewrite a description for a story asset based on a specific instruction.
Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{
  "rewritten_text": "The rewritten text goes here."
}

Now, rewrite the text based on the instruction.
PROMPT,
					'user_prompt' => <<<PROMPT
Instruction: "{instruction}"

Original Text:
"{text}"
PROMPT,
					'placeholders' => json_encode(['{instruction}', '{text}']),
					'options' => json_encode([
						'character' => [
							'detailed_appearance' => "Rewrite the following character description to be more visually detailed. Focus on specific physical features, facial expressions, and their overall presence. Also remove any instructions or notes from the original text.",
							'focus_clothing' => "Expand on the character's clothing and accessories. Describe the style, fabric, color, and condition of what they are wearing in detail. Also remove any instructions or notes from the original text.",
							'add_personality' => "Rewrite the description to hint at the character's personality through their appearance and posture. Show, don't just tell, their traits (e.g., nervous, confident, kind). Also remove any instructions or notes from the original text.",
							'simplify' => "Simplify the following description. Use clearer, more concise language suitable for a younger audience or for a quick introduction. Also remove any instructions or notes from the original text.",
							'poetic' => "Rewrite the description in a more poetic and evocative style. Use figurative language and sensory details to create a stronger mood. Also remove any instructions or notes from the original text.",
							'grammar' => 'Correct any grammatical errors, improve the sentence structure, and enhance the clarity of the following text. Act as a professional editor. Also remove any instructions or notes from the original text.',
						],
						'place' => [
							'atmospheric' => "Rewrite the following place description to be more atmospheric. Focus on the mood, lighting, weather, and overall feeling of the location. Also remove any instructions or notes from the original text.",
							'focus_architecture' => "Expand on the architectural details of the place. Describe the buildings, materials, shapes, and style in greater detail. Also remove any instructions or notes from the original text.",
							'add_sensory' => "Enrich the description by adding sensory details. What does it smell, sound, or feel like to be in this place? Also remove any instructions or notes from the original text.",
							'simplify' => "Simplify the following description. Use clearer, more concise language suitable for a younger audience or for a quick overview. Also remove any instructions or notes from the original text.",
							'historical' => "Rewrite the description to include hints of its history or past events. Suggest a sense of age, use, or abandonment. Also remove any instructions or notes from the original text.",
							'grammar' => 'Correct any grammatical errors, improve the sentence structure, and enhance the clarity of the following text. Act as a professional editor. Also remove any instructions or notes from the original text.',
						],
					]),
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.character.describe',
					'label' => 'Story Character Description Generation',
					'description' => 'Generates detailed descriptions for characters based on the story text.',
					'system_prompt' => <<<PROMPT
You are a character designer. Based on the full story text and the specific page contexts provided below, create a detailed visual description for each of the listed characters.
Focus on their physical appearance, clothing, and physique with attention to detail, as they appear in the pages they are mentioned in.

Full Story Text (for overall context):
---
{fullStoryText}
---

Character Appearances by Page:
---
{characterContext}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must contain a 'characters' array, and each object in the array must have a 'name' and 'description' key.
The 'name' must exactly match one of the names from the provided list.
The JSON object must follow this exact structure:
{
  "characters": [
    {
      "name": "Character Name",
      "description": "A detailed description of the character's appearance, including their clothes and physique."
    }
  ]
}

Now, generate the character descriptions based on their specific appearances in the story.
PROMPT,
					'user_prompt' => '',
					'placeholders' => json_encode(['{fullStoryText}', '{characterContext}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.core.generate',
					'label' => 'Story Core Generation',
					'description' => 'Generates the main story structure: title, description, character/place names, and page content.',
					'system_prompt' => <<<PROMPT
You are a creative storyteller. Based on the following instructions, create a complete story.
The story must have a title, a short description, a list of characters, a list of places, and a series of pages.
The number of pages must be exactly {numPages}.

VERY IMPORTANT INSTRUCTIONS:
1.  Instructions from the user: "{instructions}"
2.  For the 'characters' and 'places' arrays, provide only the 'name'. Leave the 'description' for each character and place as an empty string (""). You will be asked to describe them in a later step.
3.  If a character's or place's appearance, clothing, age or state changes during the story, you MUST create a separate entry for each version with a descriptive name (e.g., "Cinderella (in rags)", "Cinderella (in a ballgown)", "Arthur (Young)", "Arthur (Old)", "The Castle (daytime)", "The Castle (under siege)").
4.  In the 'pages' array, you MUST reference the specific version of the character or place that appears on that page.

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure (the example shows how to handle character variations):
{
  "title": "A string for the story title.",
  "description": "A short description of the story.",
  "characters": [
    {
      "name": "Character Name (Appearance 1)",
      "description": ""
    },
    {
      "name": "Character Name (Appearance 2)",
      "description": ""
    }
  ],
  "places": [
    {
      "name": "Place Name (State 1)",
      "description": ""
    }
  ],
  "pages": [
    {
      "content": "The text for this page of the story.",
      "characters": ["Character Name (Appearance 1)"],
      "places": ["Place Name (State 1)"]
    }
  ]
}

Now, generate the story based on the user's instructions.
PROMPT,
					'user_prompt' => '',
					'placeholders' => json_encode(['{numPages}', '{instructions}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.dictionary.generate',
					'label' => 'Story Dictionary Generation',
					'description' => 'Generates dictionary entries for a story based on its text and level.',
					'system_prompt' => <<<PROMPT
You are an expert linguist and teacher. Based on the following story text and user request, create a list of dictionary entries.
For each entry, provide the word and a simple explanation suitable for the specified language level mentioned in the text.

---
{userPrompt}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{
  "dictionary": [
    {
      "word": "The word from the text",
      "explanation": "A simple explanation of the word, tailored to the CEFR level."
    }
  ]
}

Now, generate the dictionary based on the provided text and user request.
PROMPT,
					'user_prompt' => '',
					'placeholders' => json_encode(['{userPrompt}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.page.image_prompt',
					'label' => 'Story Page Image Prompt Generation',
					'description' => 'Generates a detailed image prompt for a story page based on its text and associated characters/places.',
					'system_prompt' => <<<PROMPT
You are an expert at writing image generation prompts for AI art models like DALL-E 3 or Midjourney.
Your task is to create a single, concise, and descriptive image prompt based on the provided context of a story page.

**Instructions:**
- Synthesize all the information to create a vivid image prompt.
- The prompt should be a single paragraph of comma-separated descriptive phrases.
- Focus on visual details: the setting, character appearance, actions, mood, and lighting.
- Include all details when describing characters and places.
- Provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
- The JSON object must follow this exact structure:
{
  "prompt": "A detailed, comma-separated list of visual descriptors for the image."
}

Now, generate the image prompt for the provided context in the specified JSON format.
PROMPT,
					'user_prompt' => <<<PROMPT
**Context:**
1.  **Page Content:**
    "{pageText}"

2.  **Scene Details:**
    {characterDescriptions}
    {placeDescriptions}

3.  **User Guidance:**
    {userInstructions}
PROMPT,
					'placeholders' => json_encode(['{pageText}', '{characterDescriptions}', '{placeDescriptions}', '{userInstructions}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.page.rewrite',
					'label' => 'Story Page Text Rewrite',
					'description' => 'Rewrites a piece of story text based on a specific style instruction.',
					'system_prompt' => <<<PROMPT
You are an expert story editor. Your task is to rewrite a piece of text based on a specific instruction.
Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{
  "rewritten_text": "The rewritten text goes here."
}

Now, rewrite the text based on the instruction.
PROMPT,
					'user_prompt' => <<<PROMPT
Instruction: "{instruction}"

Original Text:
"{text}"
PROMPT,
					'placeholders' => json_encode(['{instruction}', '{text}']),
					'options' => json_encode([
						'simplify' => 'Simplify the following text for a younger audience (around A2-B1 CEFR level). Make the sentences shorter and use simpler vocabulary, but keep the original meaning.',
						'descriptive' => 'Rewrite the following text to be more descriptive and poetic. Use vivid imagery, sensory details, and figurative language to paint a richer picture for the reader.',
						'dramatic' => 'Rewrite the following text to be more dramatic and suspenseful. Build tension, use shorter, impactful sentences, and focus on the emotional stakes.',
						'perspective' => 'Rewrite the following text from the perspective of one of the characters. You will need to infer which character to use or add a note about it. Use "I" and focus on their internal thoughts and feelings.',
						'dialogue' => 'Expand the following text by adding more dialogue between the characters. Make the conversation feel natural and reveal character or advance the plot.',
						'concise' => 'Rewrite the following text to be more concise and to the point. Remove any unnecessary words, filler, or redundant phrases without losing the core meaning.',
						'thoughts' => 'Expand on the following text by delving into the character\'s inner thoughts and feelings. Show, don\'t just tell, what they are experiencing emotionally and mentally.',
						'present_tense' => 'Rewrite the following text in the present tense.',
						'past_tense' => 'Rewrite the following text in the past tense.',
						'grammar' => 'Correct any grammatical errors, improve the sentence structure, and enhance the clarity of the following text. Act as a professional editor.',
					]),
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.place.describe',
					'label' => 'Story Place Description Generation',
					'description' => 'Generates detailed descriptions for places based on the story text.',
					'system_prompt' => <<<PROMPT
You are a world builder. Based on the full story text and the specific page contexts provided below, create a detailed visual description for each of the listed places.
Focus on the appearance, atmosphere, and key features of each location as it appears in the pages it is mentioned in.

Full Story Text (for overall context):
---
{fullStoryText}
---

Place Appearances by Page:
---
{placeContext}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must contain a 'places' array, and each object in the array must have a 'name' and 'description' key.
The 'name' must exactly match one of the names from the provided list.
The JSON object must follow this exact structure:
{
  "places": [
    {
      "name": "Place Name",
      "description": "A detailed description of the place's appearance and atmosphere."
    }
  ]
}

Now, generate the place descriptions based on their specific appearances in the story.
PROMPT,
					'user_prompt' => '',
					'placeholders' => json_encode(['{fullStoryText}', '{placeContext}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
				[
					'name' => 'story.quiz.generate',
					'label' => 'Story Quiz Generation',
					'description' => 'Generates multiple-choice quiz questions for a story.',
					'system_prompt' => <<<PROMPT
You are an expert teacher. Based on the following story text and user request, create a list of multiple-choice quiz questions.
For each entry, provide the question and a list of 4 answers as a single string with newlines.
Mark the correct answer by adding an asterisk (*) at the end of the line.

---
{userPrompt}
---

Please provide the output in a single, valid JSON object. Do not include any text, markdown, or explanation outside of the JSON object itself.
The JSON object must follow this exact structure:
{
  "quiz": [
    {
      "question": "The question about the story.",
      "answers": "a) Answer one\\nb) Answer two\\nc) The correct answer*\\nd) Answer four"
    }
  ]
}

Now, generate the quiz based on the provided text and user request.
PROMPT,
					'user_prompt' => '',
					'placeholders' => json_encode(['{userPrompt}']),
					'options' => null, // Added this line
					'created_at' => now(),
					'updated_at' => now(),
				],
			]);
		}
	}
