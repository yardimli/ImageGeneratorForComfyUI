<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Image Editor</title>
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<!-- Tailwind CSS via CDN -->
	<script src="https://cdn.tailwindcss.com"></script>
	<!-- Fabric.js via CDN -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
	<!-- Cropper.js for the cropping modal -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"/>
	@yield('styles')
</head>
<body class="bg-gray-100">
@yield('content')
@yield('scripts')
</body>
</html>
