@echo off
setlocal
cd /d %~dp0\desktop

python -m pip install --upgrade pip
python -m pip install pyinstaller -r requirements.txt
pyinstaller --noconfirm --onefile --name LibreAudioChat desktop_app.py

echo Built exe under %cd%\dist\LibreAudioChat.exe
pause
endlocal
