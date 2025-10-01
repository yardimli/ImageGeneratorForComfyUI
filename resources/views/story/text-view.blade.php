<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Text View: {{ $story->title }}</title>
	<style>
      html, body {
          margin: 0;
          padding: 0;
          height: 100%;
          width: 100%;
          overflow: hidden; /* Prevent scrollbars on body */
      }
      textarea {
          width: 100%;
          height: 100%;
          border: none;
          padding: 1rem;
          box-sizing: border-box; /* Include padding in width/height */
          font-family: monospace;
          font-size: 1rem;
          line-height: 1.5;
          resize: none; /* Disable resizing handle */
          outline: none; /* Remove focus outline */
      }
	</style>
</head>
<body>
<textarea readonly>{{ $textOutput }}</textarea>
</body>
</html>
