@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h1>LLM Prompt Management</h1>
		</div>
		
		@if(session('success'))
			<div class="alert alert-success">
				{{ session('success') }}
			</div>
		@endif
		
		<div class="card">
			<div class="card-body">
				<p class="card-text">
					This page allows you to view and edit the master prompts used throughout the application for various AI generation tasks.
					Changes made here will affect the output of the AI. Placeholders like <code>{variable}</code> are replaced with dynamic content by the application.
				</p>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
						<tr>
							<th>Name (Key)</th>
							<th>Label</th>
							<th>Description</th>
							<th>Actions</th>
						</tr>
						</thead>
						<tbody>
						@foreach($prompts as $prompt)
							<tr>
								<td><code>{{ $prompt->name }}</code></td>
								<td>{{ $prompt->label }}</td>
								<td>{{ $prompt->description }}</td>
								<td>
									<a href="{{ route('llm-prompts.edit', $prompt) }}" class="btn btn-sm btn-primary">Edit</a>
								</td>
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
@endsection
