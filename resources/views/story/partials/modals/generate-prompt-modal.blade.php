<div class="modal fade" id="generatePromptModal" tabindex="-1" aria-labelledby="generatePromptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="generatePromptModalLabel">Generate Image Prompt with AI</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="prompt-generator-form">
					<div class="mb-3">
						<label for="prompt-instructions" class="form-label">Additional Instructions (Optional)</label>
						<textarea class="form-control" id="prompt-instructions" rows="3" placeholder="e.g., focus on the character's sad expression, make the lighting dramatic"></textarea>
					</div>
					<div class="mb-3">
						<label for="prompt-model" class="form-label">AI Model</label>
						<select class="form-select" id="prompt-model">
							@if(empty($models))
								<option value="" disabled>Could not load models.</option>
							@else
								@foreach($models as $model)
									<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
								@endforeach
							@endif
						</select>
					</div>
					{{-- START MODIFICATION: Add a textarea for the full, editable prompt. --}}
					<div class="mb-3">
						<label for="full-prompt-text" class="form-label">Full Prompt Sent to AI (Live Preview, Editable)</label>
						<textarea class="form-control" id="full-prompt-text" rows="12" style="font-family: monospace; font-size: 0.8rem;"></textarea>
					</div>
					{{-- END MODIFICATION --}}
					<button type="button" class="btn btn-primary" id="write-prompt-btn">
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						Write with AI
					</button>
				</div>
				<div id="prompt-result-area" class="d-none mt-4">
					<div class="mb-3">
						<label for="generated-prompt-text" class="form-label">Generated Prompt (you can edit this)</label>
						<textarea class="form-control" id="generated-prompt-text" rows="5"></textarea>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success d-none" id="update-prompt-btn">Update Prompt</button>
			</div>
		</div>
	</div>
</div>
