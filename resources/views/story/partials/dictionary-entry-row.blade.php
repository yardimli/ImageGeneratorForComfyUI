<div class="row dictionary-entry-row mb-3">
	<div class="col-md-3">
		<label class="form-label">Word</label>
		<input type="text" name="dictionary[{{ $index }}][word]" class="form-control" value="{{ $entry->word ?? '' }}" placeholder="e.g., Brave">
	</div>
	<div class="col-md-8">
		<label class="form-label">Explanation</label>
		<textarea name="dictionary[{{ $index }}][explanation]" class="form-control" rows="1" placeholder="A person who is ready to face danger or pain; showing courage.">{{ $entry->explanation ?? '' }}</textarea>
	</div>
	<div class="col-md-1 d-flex align-items-end">
		<button type="button" class="btn btn-danger remove-row-btn w-100">Remove</button>
	</div>
</div>
