<?php
/**
 * Standalone error page (rendered without the layout, so it is fully
 * self-contained). Never displays exception details in production.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f6f7fb; color: #1f2937;
               display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        .box { text-align: center; max-width: 480px; padding: 2rem; }
        h1 { font-size: 2.5rem; margin-bottom: .5rem; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Something went wrong</h1>
        <p>An unexpected error occurred. The issue has been logged. No sensitive data was exposed.</p>
        <p><a href="/">Return to the app</a></p>
    </div>
</body>
</html>
