@echo off
cd /d C:\laragon\www\hms
powershell -ExecutionPolicy Bypass -File scripts\health-check-laravel.ps1 %*
