# get php 8.1 specific version
$API = (Invoke-WebRequest -Uri "https://www.php.net/releases/index.php?json&version=8.1") | ConvertFrom-Json

# php windows download
$PHPRuntimeUrl = "https://windows.php.net/downloads/releases/php-" + $API.version + "-Win32-vs16-x64.zip"
$ComposerUrl = "https://getcomposer.org/download/latest-stable/composer.phar"

# create dir
New-Item -Path "downloads" -ItemType Directory -Force | Out-Null

# download php
if (-not(Test-Path "downloads\php.zip"))
{
    Write-Host "Downloading PHP ..."
    Invoke-WebRequest $PHPRuntimeUrl -OutFile "downloads\php.zip"
}

# extract php
New-Item -Path "runtime" -ItemType Directory -Force | Out-Null
Write-Host "Extracting php.zip ..."
Expand-Archive -Path "downloads/php.zip" -DestinationPath "runtime" -Force
# make php.ini
Move-Item -Path "runtime\php.ini-production" -Destination "runtime\php.ini" -Force
$OriginINI = Get-Content -Path "runtime\php.ini" -Raw
$OriginINI = $OriginINI -replace ';extension=openssl', 'extension=openssl'
$OriginINI = $OriginINI -replace ';extension=curl', 'extension=curl'
$OriginINI = $OriginINI -replace ';extension=mbstring', 'extension=mbstring'
$OriginINI = $OriginINI -replace ';extension=sodium', 'extension=sodium'
$OriginINI = $OriginINI -replace ';extension_dir = "./"', ('extension_dir = "' + (Split-Path -Parent $MyInvocation.MyCommand.Definition) + '\..\runtime\ext"')
$OriginINI | Set-Content -Path "runtime\php.ini"

# download composer
if (-not(Test-Path "runtime\composer.phar"))
{
    Write-Host "Downloading composer ..."
    Invoke-WebRequest $ComposerUrl -OutFile "downloads\composer.phar"
    Move-Item -Path "downloads\composer.phar" -Destination "runtime\composer.phar" -Force
}

# create runtime\composer.ps1
Set-Content -Path 'runtime\composer.ps1' -Value 'Start-Process "runtime\php.exe" ("runtime\composer.phar " + $args) -NoNewWindow -Wait' -Encoding UTF8

Write-Host "Successfully downloaded PHP and Composer !" -ForegroundColor Green
Write-Host "Use static-php-cli: bin/spc" -ForegroundColor Green
Write-Host "Use php:            runtime/php" -ForegroundColor Green
Write-Host "Use composer:       runtime/composer" -ForegroundColor Green
Write-Host ""
Write-Host "Don't forget installing composer dependencies ('runtime/composer install') before using static-php-cli !" -ForegroundColor Cyan