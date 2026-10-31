@echo off
setlocal
title Cafe Kamaal - Push to GitHub

echo ========================================================
echo        Cafe Kamaal Hotel Bill Calculator
echo            Push Project to GitHub
echo ========================================================
echo.

:: Detect git executable (use system git if installed, or local mingit)
set "GIT_CMD=git"
where git >nul 2>nul
if %errorlevel% neq 0 (
    if exist "%~dp0..\mingit\cmd\git.exe" (
        set "GIT_CMD=%~dp0..\mingit\cmd\git.exe"
    ) else (
        echo [ERROR] Git is not found on your system.
        pause
        exit /b 1
    )
)

echo [OK] Using Git: %GIT_CMD%
echo.

set /p REPO_URL="Enter your GitHub Repository URL (e.g. https://github.com/username/cafe-kamaal.git): "

if "%REPO_URL%"=="" (
    echo [ERROR] No URL entered. Aborting.
    pause
    exit /b 1
)

echo.
echo Connecting to: %REPO_URL% ...
cd /d "%~dp0"

"%GIT_CMD%" remote remove origin 2>nul
"%GIT_CMD%" remote add origin %REPO_URL%
"%GIT_CMD%" branch -M main
echo Pushing code to GitHub (main branch)...
"%GIT_CMD%" push -u origin main

if %errorlevel% equ 0 (
    echo.
    echo ========================================================
    echo   SUCCESS! Your project is now live on GitHub!
    echo ========================================================
) else (
    echo.
    echo [NOTICE] Push completed with status code %errorlevel%.
    echo If prompted to sign in, please complete the GitHub authentication in your browser.
)

echo.
pause
