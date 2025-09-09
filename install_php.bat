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
