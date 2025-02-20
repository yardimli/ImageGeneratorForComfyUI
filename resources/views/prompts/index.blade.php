<!DOCTYPE html>
<html data-bs-theme="dark">
<head>
	<title>ChatGPT Prompt Generator</title>
	<link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
	<link href="{{ asset('css/style.css') }}" rel="stylesheet">
</head>
<body>
<div class="container py-4">
	<div class="card mb-4">
		<div class="card-header">
			<h3 class="mb-0">ChatGPT Prompt Generator</h3>
		</div>
		<div class="card-body">
			<form id="promptForm" method="POST" action="{{ route('prompts.generate') }}">
				@csrf
				<div class="row mb-3">
					<div class="col-md-5">
						<label class="form-label">Templates</label>
						<select class="form-select" name="template" id="template">
							@foreach ($templates as $template)
								<option value="{{ $template['content'] }}">
									{{ $template['name'] }}
								</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-3">
						<label class="form-label">Answer precision</label>
						<select class="form-select" name="precision">
							<option value="Specific">Specific</option>
							<option value="Normal">Normal</option>
							<option value="Dreamy" selected>Dreamy</option>
							<option value="Hallucinating">Hallucinating</option>
						</select>
					</div>
					
					<div class="col-md-2">
						<label class="form-label">Response count</label>
						<input type="number" class="form-control" name="count" value="4" min="1">
					</div>
					
					<div class="col-md-2">
						<label class="form-label">Render Count</label>
						<input type="number" class="form-control" name="render_each_prompt_times" value="1" min="1">
					</div>
				
				</div>
				
				<div class="row mb-3">
					<div class="col-md-4">
						<label class="form-label">Aspect Ratio</label>
						<select class="form-select" name="aspect_ratio" id="aspectRatio">
							<optgroup label="1MP">
								<option value="1:1-1024" selected>1:1 (1024 x 1024)</option>
								<option value="3:2-1024">3:2 (1216 x 832)</option>
								<option value="4:3-1024">4:3 (1152 x 896)</option>
								<option value="16:9-1024">16:9 (1344 x 768)</option>
								<option value="21:9-1024">21:9 (1536 x 640)</option>
							</optgroup>
							<optgroup label="2MP">
								<option value="1:1-1408">1:1 (1408 x 1408)</option>
								<option value="3:2-1408">3:2 (1728 x 1152)</option>
								<option value="4:3-1408">4:3 (1664 x 1216)</option>
								<option value="16:9-1408">16:9 (1920 x 1088)</option>
								<option value="21:9-1408">21:9 (2176 x 960)</option>
							</optgroup>
						</select>
					</div>
					<div class="col-md-4">
						<label class="form-label">Width</label>
						<input type="number" class="form-control" name="width" id="width" value="1024">
					</div>
					<div class="col-md-4">
						<label class="form-label">Height</label>
						<input type="number" class="form-control" name="height" id="height" value="1024">
					</div>
				</div>
				
				<div class="mb-3">
					<label class="form-label">Original Prompt</label>
					<textarea class="form-control" name="original_prompt" rows="2"
					          placeholder="This text will replace {prompt} in the template"></textarea>
				</div>
				
				<div class="mb-3">
					<label class="form-label">Prompt</label>
					<textarea class="form-control" name="prompt" rows="4"
					          placeholder="ChatGPT prompt (Try some templates for inspiration)"></textarea>
				</div>
				
				<div class="row mb-3">
					
					<div class="col-md-6">
						<label class="form-label">Prepend generated prompt with</label>
						<input type="text" class="form-control" name="prepend_text">
					</div>
					<div class="col-md-6">
						<label class="form-label">Append generated prompt with</label>
						<input type="text" class="form-control" name="append_text">
					</div>
				</div>
				
				<div class="row mb-3">
					<div class="col-md-6">
						<div class="form-check">
							<input type="hidden" name="generate_original_prompt" value="0">
							<input class="form-check-input" type="checkbox" name="generate_original_prompt" id="generateOriginal" value="1">
							<label class="form-check-label" for="generateOriginal">
								Generate original prompt also
							</label>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-check">
							<input type="hidden" name="append_to_prompt" value="0">
							<input class="form-check-input" type="checkbox" name="append_to_prompt" id="appendToPrompt" value="1">
							<label class="form-check-label" for="appendToPrompt">
								Append to original prompt instead of replacing it
							</label>
						</div>
					</div>
				</div>
				
				<div class="text-end">
					<button type="submit" class="btn btn-primary">Generate</button>
				</div>
			</form>
		</div>
		
		<div id="resultContainer" class="result-container d-none">
			<!-- Results will be inserted here -->
		</div>
	</div>
	
	
	<div class="card mb-4">
		<div class="card-header">
			<h3 class="mb-0">Saved Settings</h3>
		</div>
		<div class="card-body">
			<select class="form-select" id="savedSettings">
				<option value="">Select saved settings</option>
				@foreach($settings as $setting)
					<option value="{{ $setting->id }}">
						{{ $setting->name ?? 'Settings from ' . $setting->created_at }}
					</option>
				@endforeach
			</select>
		</div>
	</div>

</div>

<script src="{{ asset('js/bootstrap.min.js') }}"></script>
<script src="{{ asset('js/script.js') }}"></script>

</body>
</html>

