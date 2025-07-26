<?php
// Set the content type to JSON for the response
	header('Content-Type: application/json');

// The directory where you want to save the images
	$uploadDir = 'uploads/';

// Basic error response function
	function errorResponse($message) {
		echo json_encode(['success' => false, 'message' => $message]);
		exit;
	}

// Check if the request method is POST
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		errorResponse('Invalid request method.');
	}

// Check if the imageData is provided
	if (!isset($_POST['imageData'])) {
		errorResponse('No image data received.');
	}

// Get the base64 encoded image data from the POST request
	$imageData = $_POST['imageData'];

// The data comes in the format: "data:image/png;base64,iVBORw0KGgo..."
// We need to remove the prefix to get the pure base64 data.
	if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
		$imageData = substr($imageData, strpos($imageData, ',') + 1);
		$type = strtolower($type[1]); // jpg, png, gif

		if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
			errorResponse('Invalid image type.');
		}

		// The base64 data might have spaces replaced with '+' which needs to be handled
		$imageData = str_replace(' ', '+', $imageData);
		$decodedImage = base64_decode($imageData);

		if ($decodedImage === false) {
			errorResponse('Base64 decode failed.');
		}
	} else {
		errorResponse('Did not match data URI with image data.');
	}

// Create the uploads directory if it doesn't exist
	if (!file_exists($uploadDir)) {
		if (!mkdir($uploadDir, 0775, true)) { // 0775 is a common permission setting
			errorResponse('Failed to create upload directory.');
		}
	}

// Generate a unique filename
	$filename = 'canvas_output_' . time() . '.' . $type;
	$filePath = $uploadDir . $filename;

// Save the image to the server
	if (file_put_contents($filePath, $decodedImage)) {
		// Success response
		echo json_encode([
			'success' => true,
			'message' => 'Image saved successfully.',
			'path' => $filePath
		]);
	} else {
		// Error response
		errorResponse('Failed to save the image to the server.');
	}
?>
