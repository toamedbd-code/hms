@echo off
cd /d C:\laragon\www\hms
set PHP_EXE=C:\laragon\bin\php\php-8.2.29-nts-Win32-vs16-x64\php.exe
set APP_KEY_VALUE=
for /f "tokens=1,* delims==" %%A in ('findstr /B /C:"APP_KEY=" .env') do (
	if /I "%%A"=="APP_KEY" set APP_KEY_VALUE=%%B
)

if "%APP_KEY_VALUE%"=="" (
	echo [%date% %time%] Skipped queue:work because APP_KEY is missing.>> storage\logs\queue-worker.log
	exit /b 1
)

%PHP_EXE% artisan queue:work --tries=3 --sleep=3 --timeout=120 >> storage\logs\queue-worker.log 2>&1
