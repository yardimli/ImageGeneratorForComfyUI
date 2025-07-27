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
									<input class="form-check-input" type="checkbox" name="pages[{{ $index }}][characters][]" value="{{ $character->id }}" id="char_{{ $index }}_{{ $character->id }}"
										{{ ($page && $page->characters->contains($character->id)) ? 'checked' : '' }}>
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
									<input class="form-check-input" type="checkbox" name="pages[{{ $index }}][places][]" value="{{ $place->id }}" id="place_{{ $index }}_{{ $place->id }}"
										{{ ($page && $page->places->contains($place->id)) ? 'checked' : '' }}>
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
					<textarea name="pages[{{ $index }}][image_prompt]" class="form-control" rows="3">{{ $page->image_prompt ?? '' }}</textarea>
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
