{{-- START NEW FILE --}}
<div class="modal fade" id="rewriteTextModal" tabindex="-1" aria-labelledby="rewriteTextModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="rewriteTextModalLabel">Rewrite Story Text with AI</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label for="rewrite-style" class="form-label">Rewrite Style</label>
					<select class="form-select" id="rewrite-style">
						<option value="simplify">Simplify for a younger audience</option>
						<option value="descriptive">Make it more descriptive and poetic</option>
						<option value="dramatic">Make it more dramatic and suspenseful</option>
						<option value="perspective">Rewrite from a different character's perspective</option>
						<option value="dialogue">Add more dialogue between characters</option>
						<option value="concise">Make it more concise and to the point</option>
						<option value="thoughts">Expand on the character's inner thoughts and feelings</option>
						<option value="present_tense">Change the tense from past to present</option>
						<option value="past_tense">Change the tense from present to past</option>
						<option value="grammar">Improve grammar and clarity</option>
					</select>
				</div>
				<div class="mb-3">
					<label for="rewrite-full-prompt" class="form-label">Full Prompt Sent to AI (Editable)</label>
					<textarea class="form-control" id="rewrite-full-prompt" rows="10" style="font-family: monospace; font-size: 0.8rem;"></textarea>
				</div>
				<div class="mb-3">
					<label for="rewrite-model" class="form-label">AI Model</label>
					<select class="form-select" id="rewrite-model">
						@if(empty($models))
							<option value="" disabled>Could not load models.</option>
						@else
							@foreach($models as $model)
								<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
							@endforeach
						@endif
					</select>
				</div>
				<button type="button" class="btn btn-primary" id="rewrite-text-btn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Rewrite with AI
				</button>
				<div id="rewrite-result-area" class="d-none mt-4">
					<div class="mb-3">
						<label for="rewritten-text" class="form-label">Rewritten Text</label>
						<textarea class="form-control" id="rewritten-text" rows="8"></textarea>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success d-none" id="replace-text-btn">Replace Text</button>
			</div>
		</div>
	</div>
</div>
{{-- END NEW FILE --}}
