@extends('layouts.app')

@section('content')
	<div class="container py-4">
		<div class="card">
			<div class="card-header">
				<h3 class="mb-0">Upscale Settings</h3>
			</div>
			<div class="card-body">
				@if(session('success'))
					<div class="alert alert-success">{{ session('success') }}</div>
				@endif
				
				<ul class="nav nav-tabs mb-3" id="modelTabs" role="tablist">
					@foreach($models as $model)
						<li class="nav-item" role="presentation">
							<button class="nav-link {{ $activeModelId == $model->id ? 'active' : '' }}"
							        id="tab-{{ $model->id }}"
							        data-bs-toggle="tab"
							        data-bs-target="#content-{{ $model->id }}"
							        type="button"
							        role="tab">
								{{ $model->name }}
								@if($activeModelId == $model->id) <span class="badge bg-success ms-1">Active</span> @endif
							</button>
						</li>
					@endforeach
				</ul>
				
				<div class="tab-content" id="modelTabsContent">
					@foreach($models as $model)
						<div class="tab-pane fade {{ $activeModelId == $model->id ? 'show active' : '' }}"
						     id="content-{{ $model->id }}"
						     role="tabpanel">
							
							<form action="{{ route('upscale-settings.update') }}" method="POST">
								@csrf
								<input type="hidden" name="upscale_model_id" value="{{ $model->id }}">
								
								<div class="alert alert-info">
									Configure settings for <strong>{{ $model->name }}</strong>.
									Clicking "Save & Activate" will make this the default upscaler.
								</div>
								
								<div class="row">
									@foreach($model->input_schema as $field)
										<div class="col-md-6 mb-3">
											<label class="form-label">{{ $field['label'] }}</label>
											
											@php
												$value = $modelSettings[$model->id][$field['name']] ?? '';
											@endphp
											
											@if($field['type'] === 'select')
												<select name="settings[{{ $field['name'] }}]" class="form-select">
													@foreach($field['options'] as $option)
														<option value="{{ $option }}" {{ $value == $option ? 'selected' : '' }}>
															{{ $option }}
														</option>
													@endforeach
												</select>
											@elseif($field['type'] === 'textarea')
												<textarea name="settings[{{ $field['name'] }}]" class="form-control" rows="2">{{ $value }}</textarea>
											@elseif($field['type'] === 'number')
												<input type="number"
												       name="settings[{{ $field['name'] }}]"
												       class="form-control"
												       value="{{ $value }}"
												       step="{{ $field['step'] ?? 'any' }}">
											@else
												<input type="text" name="settings[{{ $field['name'] }}]" class="form-control" value="{{ $value }}">
											@endif
											
											@if(isset($field['description']))
												<div class="form-text">{{ $field['description'] }}</div>
											@endif
										</div>
									@endforeach
								</div>
								
								<div class="mt-3">
									<button type="submit" class="btn btn-primary">Save & Activate {{ $model->name }}</button>
								</div>
							</form>
						</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>
@endsection
