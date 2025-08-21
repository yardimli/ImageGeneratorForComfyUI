{{--
Represents a single dictionary entry row within a page card in the story editor.
- $index: The index of the parent page.
- $d_index: The index of this dictionary entry within the page.
- $entry: The StoryDictionary model instance, or null for a new template row.
--}}
<div class="input-group input-group-sm mb-2 dictionary-entry-row">
	<input type="text"
	       name="pages[{{ $index }}][dictionary][{{ $d_index }}][word]"
	       class="form-control"
	       value="{{ $entry->word ?? '' }}"
	       placeholder="Word"
	       aria-label="Word">

	<input type="text"
	       name="pages[{{ $index }}][dictionary][{{ $d_index }}][explanation]"
	       class="form-control"
	       value="{{ $entry->explanation ?? '' }}"
	       placeholder="Simple explanation"
	       aria-label="Explanation">

	<button type="button" class="btn btn-outline-danger remove-dictionary-entry-btn" title="Remove Entry">&times;</button>
</div>
