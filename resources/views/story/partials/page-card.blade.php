<div class="card mb-3 page-card" data-id="{{ $page->id ?? '' }}">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h5 class="mb-0">Page <span class="page-number">{{ $page->page_number ?? 'New' }}</span></h5>
		<button type="button" class="btn-close remove-page-btn"></button>
	</div>
	<div class="card-body">
		<input type="hidden" name="pages[{{ $index }}][id]" value="{{ $page->id ?? '' }}">
		<div class="row">
			<div class="col-md-8">
				<div class="mb-3">
					<label class="form-label">Story Text</label>
					<textarea name="pages[{{ $index }}][story_text]" class="form-control" rows="8">{{ $page->story_text ?? '' }}</textarea>
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
				</div>
				
				<div class="row">
					<div class="col-md-6">
						<label class="form-label">Characters on this page</label>
						<div class="form-check-group border p-2 rounded">
							@forelse($story->characters as $character)
								<div class="form-check">
									<input class="form-check-input character-checkbox" type="checkbox" name="pages[{{ $index }}][characters][]" value="{{ $character->id }}" id="char_{{ $index }}_{{ $character->id }}"
									       {{ ($page && $page->characters->contains($character->id)) ? 'checked' : '' }} data-description="{{ e($character->name . ": " . $character->description) }}">
									<label class="form-check-label" for="char_{{ $index }}_{{ $character->id }}">{{ $character->name }}</label>
								</div>
							@empty
								<p class="text-muted small">No characters created yet. <a href="{{ route('stories.characters', $story) }}">Add characters</a>.</p>
							@endforelse
						</div>
					</div>
					<div class="col-md-6">
						<label class="form-label">Places on this page</label>
						<div class="form-check-group border p-2 rounded">
							@forelse($story->places as $place)
								<div class="form-check">
									<input class="form-check-input place-checkbox" type="checkbox" name="pages[{{ $index }}][places][]" value="{{ $place->id }}" id="place_{{ $index }}_{{ $place->id }}"
									       {{ ($page && $page->places->contains($place->id)) ? 'checked' : '' }} data-description="{{ e($place->name . ": " .$place->description) }}">
									<label class="form-check-label" for="place_{{ $index }}_{{ $place->id }}">{{ $place->name }}</label>
								</div>
							@empty
								<p class="text-muted small">No places created yet. <a href="{{ route('stories.places', $story) }}">Add places</a>.</p>
							@endforelse
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<label class="form-label">Page Image
					@if ($page && $page->prompt_data->upscale_url)
						<span class="badge bg-success" title="Image has been upscaled">Upscaled</span>
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
