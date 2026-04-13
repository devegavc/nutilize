<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .error-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
        }

        .error-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .error-message {
            color: #666;
            margin: 20px 0;
            line-height: 1.6;
        }

        .retry-button {
            background: #f5576c;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .retry-button:hover {
            background: #f093fb;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Database Error</h1>
        <p class="error-message">
            {{ $message ?? 'A database error occurred. Please try again later.' }}
        </p>

        <button class="retry-button" onclick="location.reload()">Try Again</button>
    </div>
</body>
</html>
