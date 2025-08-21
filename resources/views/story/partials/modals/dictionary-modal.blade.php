{{--
    Modal for generating dictionary entries for a specific story page.
    This is triggered from the page-card in the story editor.
--}}
<div class="modal fade" id="dictionaryModal" tabindex="-1" aria-labelledby="dictionaryModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="dictionaryModalLabel">Generate Dictionary Entries with AI</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				{{-- The JS will populate this with an initial request --}}
				<div class="mb-3">
					<label for="dictionary-prompt-text" class="form-label">Your Request</label>
					<textarea class="form-control" id="dictionary-prompt-text" rows="4" placeholder="e.g., Create 5 simple entries for difficult words."></textarea>
					<div class="form-text">
						The AI will use this request, the text of the current page, and a list of existing words to generate new entries.
					</div>
				</div>
				
				<div class="mb-3">
					<label for="dictionary-model" class="form-label">AI Model</label>
					<select class="form-select" id="dictionary-model">
						@if(empty($models))
							<option value="" disabled>Could not load models.</option>
						@else
							@foreach($models as $model)
								<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
							@endforeach
						@endif
					</select>
				</div>
				
				{{-- This container is available if we want to show results in the modal in the future. --}}
				<div id="dictionary-result-container" class="mt-4"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="generate-dictionary-btn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Generate & Add to Page
				</button>
			</div>
		</div>
	</div>
</div>
