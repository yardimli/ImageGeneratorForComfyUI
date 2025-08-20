@extends('layouts.bootstrap-app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h1>Edit LLM Prompt</h1>
				<p class="text-muted">
					Editing prompt: <code>{{ $prompt->name }}</code>. <a href="{{ route('llm-prompts.index') }}">Return to List</a>.
				</p>
			</div>
		</div>
		
		@if(session('success'))
			<div class="alert alert-success">
				{{ session('success') }}
			</div>
		@endif
		
		<form action="{{ route('llm-prompts.update', $prompt) }}" method="POST">
			@csrf
			@method('PUT')
			
			<div class="card">
				<div class="card-body">
					<div class="mb-3">
						<label for="label" class="form-label">Label</label>
						<input type="text" id="label" name="label" class="form-control" value="{{ old('label', $prompt->label) }}" required>
					</div>
					
					<div class="mb-3">
						<label for="description" class="form-label">Description</label>
						<textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $prompt->description) }}</textarea>
					</div>
					
					<div class="mb-3">
						<label for="system_prompt" class="form-label">System Prompt / Instructions</label>
						<textarea id="system_prompt" name="system_prompt" class="form-control" rows="10" style="font-family: monospace; font-size: 0.9rem;">{{ old('system_prompt', $prompt->system_prompt) }}</textarea>
						<small class="form-text text-muted">This often contains the core instructions, role-playing, and JSON structure requirements for the AI.</small>
					</div>
					
					<div class="mb-3">
						<label for="user_prompt" class="form-label">User Prompt Template</label>
						<textarea id="user_prompt" name="user_prompt" class="form-control" rows="10" style="font-family: monospace; font-size: 0.9rem;">{{ old('user_prompt', $prompt->user_prompt) }}</textarea>
						<small class="form-text text-muted">This is the main prompt template. Use placeholders like <code>{variable}</code> which will be replaced by the application.</small>
					</div>
					
					@if($prompt->placeholders)
						<div class="mb-3">
							<label class="form-label">Available Placeholders:</label>
							<div>
								@foreach($prompt->placeholders as $placeholder)
									<span class="badge bg-secondary me-1"><code>{{ $placeholder }}</code></span>
								@endforeach
							</div>
						</div>
					@endif
				</div>
				<div class="card-footer text-end">
					<button type="submit" class="btn btn-primary">Save Changes</button>
				</div>
			</div>
		</form>
	</div>
@endsection
