#!/bin/bash
# Start PHP development server with clean URL routing

cd "$(dirname "$0")"

echo "🚀 Starting Alive Church website..."
echo "📍 URL: http://localhost:8999"
echo "🛑 Press Ctrl+C to stop"
echo ""

php -S localhost:8999 router.php
