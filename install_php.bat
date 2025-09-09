// Updated install_php.bat script to handle potential failures and provide guidance.
@echo off
:: Check if Chocolatey is installed
choco -v >nul 2>&1 || (
    echo Installing Chocolatey...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Set-ExecutionPolicy RemoteSigned; iwr https://chocolatey.org/install.ps1 | iex"
)
echo Installing PHP...
choco install php -y
echo PHP installed. Check version:
php --version

:: If PHP is not found, instruct user to run this script again or manually install.
if errorlevel 1 (
    echo "PHP installation failed. Please ensure Chocolatey is installed and try again."
    pause
)
