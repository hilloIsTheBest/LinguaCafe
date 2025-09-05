@echo off
setlocal
cd /d %~dp0

REM Load CHAT_URL and AUDIOBOOKS_API_URL from .env if present
if exist .env (
  for /f "usebackq tokens=1,2 delims==" %%A in (".env") do (
    if /I "%%A"=="CHAT_URL" set CHAT_URL=%%B
    if /I "%%A"=="AUDIOBOOKS_API_URL" set AUDIOBOOKS_API_URL=%%B
  )
)

if "%CHAT_URL%"=="" (
  echo Optional: enter Chat URL (e.g. http://localhost:8000 for LinguaCafe). Leave blank to skip:
  set /p CHAT_URL=
)

echo Installing desktop app dependencies (first run may take a while)...
python -m pip install -r desktop\requirements.txt >nul 2>&1

set CHAT_URL=%CHAT_URL%
set AUDIOBOOKS_API_URL=%AUDIOBOOKS_API_URL%

if exist desktop\dist\LibreAudioChat.exe (
  desktop\dist\LibreAudioChat.exe
) else (
  python desktop\desktop_app.py
)

endlocal
