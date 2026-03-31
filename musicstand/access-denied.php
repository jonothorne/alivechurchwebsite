<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Music Stand</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 2rem;
        }
        .container {
            text-align: center;
            max-width: 500px;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        p {
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.875rem 2rem;
            background: #6366f1;
            color: #fff;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #4f46e5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🎵</div>
        <h1>Access Restricted</h1>
        <p>
            Music Stand is available for worship team members. If you believe you should have access,
            please contact the worship leader or church administrator to be added to the worship team.
        </p>
        <a href="/" class="btn">Return Home</a>
    </div>
</body>
</html>
