@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<h1>Create a New Story</h1>
		
		<div class="card">
			<div class="card-body">
				<form action="{{ route('stories.store') }}" method="POST">
					@csrf
					<div class="mb-3">
						<label for="title" class="form-label">Title</label>
						<input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
						@error('title')
						<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>
					
					{{-- START MODIFICATION: Add a select input for the story level. --}}
					<div class="mb-3">
						<label for="level" class="form-label">English Proficiency Level (CEFR)</label>
						<select class="form-select @error('level') is-invalid @enderror" id="level" name="level" required>
							<option value="" disabled {{ old('level') ? '' : 'selected' }}>Select a level...</option>
							
							<optgroup label="A - Basic User">
								<option value="A1" {{ old('level') == 'A1' ? 'selected' : '' }}>
									A1 - Beginner: Can understand and use familiar everyday expressions.
								</option>
								<option value="A2" {{ old('level') == 'A2' ? 'selected' : '' }}>
									A2 - Elementary: Can understand sentences and frequently used expressions on familiar topics.
								</option>
							</optgroup>
							
							<optgroup label="B - Independent User">
								<option value="B1" {{ old('level') == 'B1' ? 'selected' : '' }}>
									B1 - Intermediate: Can understand the main points of clear text on familiar matters.
								</option>
								<option value="B2" {{ old('level') == 'B2' ? 'selected' : '' }}>
									B2 - Upper-Intermediate: Can understand the main ideas of complex text on both concrete and abstract topics.
								</option>
							</optgroup>
							
							<optgroup label="C - Proficient User">
								<option value="C1" {{ old('level') == 'C1' ? 'selected' : '' }}>
									C1 - Advanced: Can understand a wide range of demanding, longer texts, and recognize implicit meaning.
								</option>
								<option value="C2" {{ old('level') == 'C2' ? 'selected' : '' }}>
									C2 - Mastery: Can understand with ease virtually everything heard or read. Can express self fluently and precisely.
								</option>
							</optgroup>
						</select>
						@error('level')
						<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>
					{{-- END MODIFICATION --}}
					
					<div class="mb-3">
						<label for="short_description" class="form-label">Short Description</label>
						<textarea class="form-control @error('short_description') is-invalid @enderror" id="short_description" name="short_description" rows="3">{{ old('short_description') }}</textarea>
						@error('short_description')
						<div class="invalid-feedback">{{ $message }}</div>
						@enderror
					</div>
					
					<div class="d-flex justify-content-end">
						<a href="{{ route('stories.index') }}" class="btn btn-secondary me-2">Cancel</a>
						<button type="submit" class="btn btn-primary">Create Story</button>
					</div>
				</form>
			</div>
		</div>
	</div>
@endsection
