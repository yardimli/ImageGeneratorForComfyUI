<div class="card mb-3 page-card" data-id="{{ $page->id ?? '' }}">
	<div class="card-header d-flex justify-content-between align-items-center">
		<div class="d-flex align-items-center gap-2">
			<h5 class="mb-0">Page <span class="page-number">{{ $page->page_number ?? 'New' }}</span></h5>
			{{-- These buttons only appear for pages that have been saved to the database. --}}
			@if(isset($page->id))
				<div class="btn-group btn-group-sm" role="group" aria-label="Page Actions">
					{{-- START MODIFICATION: Removed formmethod attribute. The buttons now inherit the form's PUT method, which the routes now accept. --}}
					<button type="submit" formaction="{{ route('stories.pages.insert-above', ['story' => $story, 'storyPage' => $page]) }}" class="btn btn-outline-secondary" title="Insert new page above">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-bar-up" viewBox="0 0 16 16">
							<path fill-rule="evenodd" d="M3.646 11.854a.5.5 0 0 0 .708 0L8 8.207l3.646 3.647a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 0 0 0 .708zM1 8a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 0 1h-13A.5.5 0 0 1 1 8z"/>
						</svg>
					</button>
					<button type="submit" formaction="{{ route('stories.pages.insert-below', ['story' => $story, 'storyPage' => $page]) }}" class="btn btn-outline-secondary" title="Insert new page below">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-bar-down" viewBox="0 0 16 16">
							<path fill-rule="evenodd" d="M3.646 4.146a.5.5 0 0 1 .708 0L8 7.793l3.646-3.647a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 0-.708zM1 8a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 0 1h-13A.5.5 0 0 1 1 8z"/>
						</svg>
					</button>
					{{-- END MODIFICATION --}}
				</div>
			@endif
		</div>
		<button type="button" class="btn-close remove-page-btn"></button>
	</div>
	<div class="card-body">
		<input type="hidden" name="pages[{{ $index }}][id]" value="{{ $page->id ?? '' }}">
		<div class="row">
			<div class="col-md-8">
				<div class="mb-3">
					<label class="form-label">Story Text</label>
					<textarea name="pages[{{ $index }}][story_text]" class="form-control story-text-textarea" rows="8">{{ $page->story_text ?? '' }}</textarea>
					
					<button type="button" class="btn btn-sm btn-outline-secondary mt-2 rewrite-story-text-btn" data-bs-toggle="modal" data-bs-target="#rewriteTextModal">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square me-1" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
						Rewrite
					</button>
				</div>
				<div class="mb-3">
					<label class="form-label">Image Prompt</label>
					<textarea name="pages[{{ $index }}][image_prompt]" class="form-control image-prompt-textarea" rows="5" data-initial-value="{{ e($page->image_prompt ?? '') }}">{{ $page->image_prompt ?? '' }}</textarea>
					<button type="button" class="btn btn-sm btn-outline-info mt-2 generate-prompt-btn" data-bs-toggle="modal" data-bs-target="#generatePromptModal">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic me-1" viewBox="0 0 16 16"><path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 3.5a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8 4.707 7.293 4Zm-.646 10.646a.5.5 0 0 0 .708 0L8 13.914l-1.06-1.06a.5.5 0 0 0-.854.353v.534a.5.5 0 0 0 .146.354l.646.646ZM.5 10.828a.5.5 0 0 0 1 0V9.157a.5.5 0 0 0-1 0v1.671Zm1.829-4.5A.5.5 0 0 0 2 6.586l.707.707a.5.5 0 0 0 .707 0L4 6.586a.5.5 0 0 0 0-.707L2.707 4.586a.5.5 0 0 0-.707 0ZM10.828.5a.5.5 0 0 0 0 1h1.671a.5.5 0 0 0 0-1h-1.671Z"/><path d="M3.5 13.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Zm9.025-5.99a1.5 1.5 0 0 0-1.025.433L6.932 12.47a1.5 1.5 0 0 0-1.025.433L3.025 15.8a.5.5 0 0 0 .854.353L5.5 14.5l.646.646a.5.5 0 0 0 .708 0L8.5 13.5l.646.646a.5.5 0 0 0 .708 0L11.5 12.5l.646.646a.5.5 0 0 0 .708 0L14.5 11.5l.646.646a.5.5 0 0 0 .854-.353l-2.882-2.882a1.5 1.5 0 0 0-1.025-.433Z"/></svg>
						Fill with AI
					</button>
					<button type="button" class="btn btn-sm btn-outline-success mt-2 draw-with-ai-btn" data-story-page-id="{{ $page->id ?? '' }}">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-palette-fill me-1" viewBox="0 0 16 16"><path d="M12.433 10.07C14.133 10.585 16 11.15 16 8a8 8 0 1 0-15.93 1.156c.224-.434.458-.85.713-1.243a4.999 4.999 0 0 1 4.213-2.333c.348-.07.705-.12 1.07-.12.41 0 .816.064 1.2.19.495.16 1.02.443 1.547.854.541.427 1.116.954 1.6 1.587zM2 8a6 6 0 1 1 11.25 3.262C11.333 10.51 9.482 9.622 8 9.622c-1.927 0-3.936.992-5.25 2.054A6.001 6.001 0 0 1 2 8z"/><path d="M8 5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm4-3a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM4.5 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM15 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg>
						Draw with AI
					</button>
					{{-- START MODIFICATION: Add new "Draw with AI v2" button --}}
					<button type="button" class="btn btn-sm btn-outline-primary mt-2 draw-with-ai-v2-btn" data-bs-toggle="modal" data-bs-target="#drawWithAiV2Modal" data-story-page-id="{{ $page->id ?? '' }}">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-images me-1" viewBox="0 0 16 16">
							<path d="M4.502 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
							<path d="M14.002 13a2 2 0 0 1-2 2h-10a2 2 0 0 1-2-2V5A2 2 0 0 1 2 3a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8a2 2 0 0 1-1.998 2zM14 2H4a1 1 0 0 0-1 1h9.002a2 2 0 0 1 2 2v7A1 1 0 0 0 15 11V3a1 1 0 0 0-1-1zM2.002 4a1 1 0 0 0-1 1v8l2.646-2.354a.5.5 0 0 1 .63-.062l2.66 1.773 3.71-3.71a.5.5 0 0 1 .577-.094l1.777 1.947V5a1 1 0 0 0-1-1h-10z"/>
						</svg>
						Draw with AI v2
					</button>
					{{-- END MODIFICATION --}}
				</div>
				
				<div class="row">
					<div class="col-md-6">
						<label class="form-label">Characters on this page</label>
						<div class="form-check-group border p-2 rounded">
							@forelse($story->characters as $character)
								{{-- START MODIFICATION: Add thumbnail and data-image-path to character checkbox --}}
								<div class="form-check d-flex align-items-center mb-1">
									<input class="form-check-input character-checkbox me-2" type="checkbox" name="pages[{{ $index }}][characters][]" value="{{ $character->id }}" id="char_{{ $index }}_{{ $character->id }}"
									       {{ ($page && $page->characters->contains($character->id)) ? 'checked' : '' }}
									       data-description="{{ e($character->name . ": " . $character->description) }}"
									       data-image-path="{{ $character->image_path ?? '' }}">
									<img src="{{ $character->image_path ? asset($character->image_path) : 'https://via.placeholder.com/48' }}" alt="{{ $character->name }}" class="rounded me-2" style="width: 48px; height: 48px; object-fit: cover;">
									<label class="form-check-label" for="char_{{ $index }}_{{ $character->id }}">{{ $character->name }}</label>
								</div>
								{{-- END MODIFICATION --}}
							@empty
								<p class="text-muted small">No characters created yet. <a href="{{ route('stories.characters', $story) }}">Add characters</a>.</p>
							@endforelse
						</div>
					</div>
					<div class="col-md-6">
						<label class="form-label">Places on this page</label>
						<div class="form-check-group border p-2 rounded">
							@forelse($story->places as $place)
								{{-- START MODIFICATION: Add thumbnail and data-image-path to place checkbox --}}
								<div class="form-check d-flex align-items-center mb-1">
									<input class="form-check-input place-checkbox" type="checkbox" name="pages[{{ $index }}][places][]" value="{{ $place->id }}" id="place_{{ $index }}_{{ $place->id }}"
									       {{ ($page && $page->places->contains($place->id)) ? 'checked' : '' }}
									       data-description="{{ e($place->name . ": " .$place->description) }}"
									       data-image-path="{{ $place->image_path ?? '' }}">
									<img src="{{ $place->image_path ? asset($place->image_path) : 'https://via.placeholder.com/48' }}" alt="{{ $place->name }}" class="rounded me-2" style="width: 48px; height: 48px; object-fit: cover;">
									<label class="form-check-label" for="place_{{ $index }}_{{ $place->id }}">{{ $place->name }}</label>
								</div>
								{{-- END MODIFICATION --}}
							@empty
								<p class="text-muted small">No places created yet. <a href="{{ route('stories.places', $story) }}">Add places</a>.</p>
							@endforelse
						</div>
					</div>
				</div>
				
				{{-- START MODIFICATION: Add dictionary section for the page. --}}
				<div class="mt-3">
					<label class="form-label">Dictionary Entries for this Page</label>
					<div class="dictionary-container border p-2 rounded">
						<div class="dictionary-entries-container" style="max-height: 200px; overflow-y: auto;">
							@if($page && $page->dictionary)
								@foreach($page->dictionary as $d_index => $entry)
									@include('story.partials.dictionary-entry-row-page', ['index' => $index, 'd_index' => $d_index, 'entry' => $entry])
								@endforeach
							@endif
						</div>
						<div class="mt-2">
							<button type="button" class="btn btn-sm btn-success add-dictionary-entry-btn">Add Entry</button>
							<button type="button" class="btn btn-sm btn-info generate-dictionary-btn" data-bs-toggle="modal" data-bs-target="#dictionaryModal" data-page-id="{{ $page->id ?? '' }}">
								Generate with AI
							</button>
						</div>
					</div>
				</div>
				{{-- END MODIFICATION --}}
			
			</div>
			<div class="col-md-4">
				<label class="form-label">Page Image
					@if ($page && isset($page->prompt_data))
						@if ($page->prompt_data->upscale_status == 2 && $page->prompt_data->upscale_url)
							<span class="badge bg-success ms-2" title="Image has been upscaled">Upscaled</span>
						@elseif ($page->prompt_data->upscale_status == 1)
							<span class="badge bg-warning ms-2" title="Image is being upscaled">Upscaling...</span>
						@endif
					@endif
				</label>
				<div class="image-upload-container mb-2 position-relative" style="min-height: 400px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
					<img src="{{ $page->image_path ?? 'https://picsum.photos/200' }}"
					     class="page-image-preview"
					     style="max-width: 100%; object-fit: contain; {{ $page && $page->image_path ? 'cursor: pointer;' : '' }}"
					     @if($page && $page->image_path && isset($page->prompt_data))
						     data-bs-toggle="modal"
					     data-bs-target="#imageDetailModal"
					     data-image-url="{{ $page->image_path }}"
					     data-prompt-id="{{ $page->prompt_data->id }}"
					     data-upscale-status="{{ $page->prompt_data->upscale_status }}"
					     data-upscale-url="{{ $page->prompt_data->upscale_url ? asset('storage/upscaled/' . $page->prompt_data->upscale_url) : '' }}"
						@endif
					>
					<div class="spinner-overlay d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center" style="background-color: rgba(var(--bs-body-color-rgb), 0.5);">
						<div class="spinner-border text-light" role="status">
							<span class="visually-hidden">Loading...</span>
						</div>
						<div class="mt-2 text-light">Generating...</div>
					</div>
				</div>
				<input type="hidden" name="pages[{{ $index }}][image_path]" class="image-path-input" value="{{ $page->image_path ?? '' }}">
				<button type="button" class="btn btn-sm btn-primary select-image-btn">Upload/Select Image</button>
				
				@php
					// Gather character image URLs for the current page, filtering out any null/empty paths.
					$characterImageUrls = $page ? $page->characters->pluck('image_path')->filter()->all() : [];
					// Prepare parameters for the Image Editor route.
					$kontextLoraParams = [
						'return_url' => route('kontext-lora.index'),
						'bg_url' => $page->image_path ?? null,
						'overlay_urls' => $characterImageUrls,
					];
				@endphp
				<a href="{{ route('image-editor.index', $kontextLoraParams) }}"
				   class="btn btn-sm btn-outline-warning {{ !($page && $page->image_path) ? 'disabled' : '' }}"
				   target="_blank"
				   title="Open in Image Editor to compose for Kontext Lora">
					Kontext Lora
				</a>
			
			</div>
		</div>
	</div>
</div>
