<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<title>Database Connection Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .solutions {
            text-align: left;
            background: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .solutions h3 {
            margin-top: 0;
            color: #333;
        }

        .solutions li {
            margin-bottom: 10px;
            color: #555;
        }

        .retry-button {
            background: #667eea;
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
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🌐</div>
        <h1>Connection Issue</h1>
        <p class="error-message">
            {{ $message ?? 'Unable to connect to the database. This could be a temporary network issue.' }}
        </p>

        <div class="solutions">
            <h3>What you can try:</h3>
            <ul>
                <li>Check your internet connection</li>
                <li>Try switching to a different network (WiFi, mobile hotspot, etc.)</li>
                <li>Wait a moment and try again</li>
                <li>If on a restricted network, you may need to use a VPN or connection pooler</li>
            </ul>
        </div>

        <button class="retry-button" onclick="location.reload()">Retry</button>
    </div>
</body>
</html>
