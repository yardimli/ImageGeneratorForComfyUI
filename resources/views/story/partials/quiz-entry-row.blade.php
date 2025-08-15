<div class="row quiz-entry-row mb-3">
	<div class="col-md-5">
		<label class="form-label">Question</label>
		<textarea name="quiz[{{ $index }}][question]" class="form-control" rows="4" placeholder="e.g., What color was the dragon?">{{ $entry->question ?? '' }}</textarea>
	</div>
	<div class="col-md-6">
		<label class="form-label">Answers</label>
		<textarea name="quiz[{{ $index }}][answers]" class="form-control" rows="4" placeholder="a) Red&#10;b) Green*&#10;c) Blue&#10;d) Yellow">{{ $entry->answers ?? '' }}</textarea>
		<small class="form-text text-muted">One answer per line. Mark the correct answer with an asterisk (*).</small>
	</div>
	<div class="col-md-1 d-flex align-items-end">
		<button type="button" class="btn btn-danger remove-row-btn w-100">Remove</button>
	</div>
</div>
