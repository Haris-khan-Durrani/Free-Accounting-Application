#!/usr/bin/env bash
set -e

echo "======================================================================"
echo "          🚀 Launching OneSol Invoice Manager Docker Stack"
echo "======================================================================"
echo ""

if [ ! -f .env ]; then
    echo "📋 Creating default .env file from .env.example..."
    cp .env.example .env
fi

echo "🐳 Building and launching containers (web, db, cache)..."
docker-compose up -d --build

echo ""
echo "======================================================================"
echo "✅ SUCCESS! OneSol Invoice Manager is running in Docker!"
echo ""
echo "🌐 Web Dashboard:    http://localhost:8080"
echo "⚡ Redis Dashboard:  http://localhost:8080/cache_admin"
echo "🔑 API Key Manager:  http://localhost:8080/api_keys"
echo "📖 User Guide:       http://localhost:8080/guide"
echo "======================================================================"
