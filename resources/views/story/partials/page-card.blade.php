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
				<div class="row">
					<div class="col-md-6">
						<label class="form-label">Characters on this page</label>
						<div class="form-check-group border p-2 rounded">
							@forelse($story->characters as $character)
								<div class="form-check">
									{{-- START MODIFICATION: Added class and data-description attribute --}}
									<input class="form-check-input character-checkbox" type="checkbox" name="pages[{{ $index }}][characters][]" value="{{ $character->id }}" id="char_{{ $index }}_{{ $character->id }}"
									       {{ ($page && $page->characters->contains($character->id)) ? 'checked' : '' }} data-description="{{ e($character->description) }}">
									{{-- END MODIFICATION --}}
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
									{{-- START MODIFICATION: Added class and data-description attribute --}}
									<input class="form-check-input place-checkbox" type="checkbox" name="pages[{{ $index }}][places][]" value="{{ $place->id }}" id="place_{{ $index }}_{{ $place->id }}"
									       {{ ($page && $page->places->contains($place->id)) ? 'checked' : '' }} data-description="{{ e($place->description) }}">
									{{-- END MODIFICATION --}}
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
				<div class="mb-3">
					<label class="form-label">Image Prompt</label>
					{{-- START MODIFICATION: Added class to textarea and added "Fill with AI" button --}}
					<textarea name="pages[{{ $index }}][image_prompt]" class="form-control image-prompt-textarea" rows="3">{{ $page->image_prompt ?? '' }}</textarea>
					<button type="button" class="btn btn-sm btn-outline-info mt-2 generate-prompt-btn" data-bs-toggle="modal" data-bs-target="#generatePromptModal">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic me-1" viewBox="0 0 16 16"><path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 3.5a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8 4.707 7.293 4Zm-.646 10.646a.5.5 0 0 0 .708 0L8 13.914l-1.06-1.06a.5.5 0 0 0-.854.353v.534a.5.5 0 0 0 .146.354l.646.646ZM.5 10.828a.5.5 0 0 0 1 0V9.157a.5.5 0 0 0-1 0v1.671Zm1.829-4.5A.5.5 0 0 0 2 6.586l.707.707a.5.5 0 0 0 .707 0L4 6.586a.5.5 0 0 0 0-.707L2.707 4.586a.5.5 0 0 0-.707 0ZM10.828.5a.5.5 0 0 0 0 1h1.671a.5.5 0 0 0 0-1h-1.671Z"/><path d="M3.5 13.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Zm9.025-5.99a1.5 1.5 0 0 0-1.025.433L6.932 12.47a1.5 1.5 0 0 0-1.025.433L3.025 15.8a.5.5 0 0 0 .854.353L5.5 14.5l.646.646a.5.5 0 0 0 .708 0L8.5 13.5l.646.646a.5.5 0 0 0 .708 0L11.5 12.5l.646.646a.5.5 0 0 0 .708 0L14.5 11.5l.646.646a.5.5 0 0 0 .854-.353l-2.882-2.882a1.5 1.5 0 0 0-1.025-.433Z"/></svg>
						Fill with AI
					</button>
					{{-- END MODIFICATION --}}
				</div>
				<label class="form-label">Page Image</label>
				<div class="image-upload-container mb-2" style="min-height: 150px; border: 2px dashed #ccc; padding: 10px; text-align: center;">
					<img src="{{ $page->image_path ?? 'https://via.placeholder.com/300' }}" class="page-image-preview" style="max-width: 100%; max-height: 200px; object-fit: contain;">
				</div>
				<input type="hidden" name="pages[{{ $index }}][image_path]" class="image-path-input" value="{{ $page->image_path ?? '' }}">
				<button type="button" class="btn btn-sm btn-primary select-image-btn">Upload/Select Image</button>
			</div>
		</div>
	</div>
</div>
