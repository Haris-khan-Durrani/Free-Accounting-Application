@echo off
title OneSol Invoice Manager — Docker Launcher
echo ======================================================================
echo           🚀 Launching OneSol Invoice Manager Docker Stack
echo ======================================================================
echo.

if not exist .env (
    echo 📋 Creating default .env file from .env.example...
    copy .env.example .env
)

echo 🐳 Building and launching containers (web, db, cache)...
docker-compose up -d --build

echo.
echo ======================================================================
echo ✅ SUCCESS! OneSol Invoice Manager is running in Docker!
echo.
echo 🌐 Web Dashboard:    http://localhost:8080
echo ⚡ Redis Dashboard:  http://localhost:8080/cache_admin
echo 🔑 API Key Manager:  http://localhost:8080/api_keys
echo 📖 User Guide:       http://localhost:8080/guide
echo ======================================================================
pause
