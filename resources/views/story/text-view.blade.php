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
          display: flex;
          flex-direction: column;
      }
      textarea {
          width: 100%;
          flex: 1;
          border: none;
          padding: 1rem;
          box-sizing: border-box; /* Include padding in width/height */
          font-family: monospace;
          font-size: 1rem;
          line-height: 1.5;
          resize: none; /* Disable resizing handle */
          outline: none; /* Remove focus outline */
      }
      footer {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: .5rem;
          border-top: 1px solid #e2e8f0;
          padding: .65rem 1rem;
          color: #64748b;
          font: 0.8rem system-ui, sans-serif;
      }
      footer img { width: 1.25rem; height: 1.25rem; }
	</style>
</head>
<body>
<textarea readonly>{{ $textOutput }}</textarea>
<footer><img src="{{ asset('images/favicon-32x32.png') }}" alt=""> DreamCover — illustrated story text view</footer>
</body>
</html>
