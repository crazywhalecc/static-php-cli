param (
    [string] ${action}
)

function AddToPath {
    param (
        [string]$pathToAdd
    )

    $currentPath = [System.Environment]::GetEnvironmentVariable('Path', 'User')

    if ($currentPath -notlike "*$pathToAdd*") {
        $newPath = $currentPath + ";$pathToAdd"
        [System.Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
        Write-Host "Added '$pathToAdd' to Path."
        Write-Host "To remove path, use: " -NoNewline
        Write-Host "bin/setup-runtime remove-path" -ForegroundColor Cyan
    } else {
        Write-Host "Path already exists."
    }
}

function RemoveFromPath {
    param (
        [string]$pathToRemove
    )

    $currentPath = [System.Environment]::GetEnvironmentVariable('Path', 'User')

    if ($currentPath -like "*$pathToRemove*") {
        $newPath = $currentPath -replace [regex]::Escape(';' + $pathToRemove), ''
        [System.Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
        Write-Host "Removed Path '$pathToRemove'"
    } else {
        Write-Host "Path '$pathToRemove' not in Path"
    }
}

# working dir
$WorkingDir = (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Definition))

if ($action -eq 'add-path') {
    AddToPath ($WorkingDir + '\runtime')
    exit 0
} elseif ($action -eq 'remove-path') {
    RemoveFromPath ($WorkingDir + '\runtime')
    exit 0
} elseif (-not($action -eq '')) {
    Write-Host ("Invalid action: " + $action) -ForegroundColor Red
    exit 1
}

# get php 8.1 specific version

# php windows download
$PHPRuntimeUrl = "https://windows.php.net/downloads/releases/archives/php-8.4.4-nts-Win32-vs17-x64.zip"
Write-Host "Downloading PHP from: " -NoNewline
Write-Host $PHPRuntimeUrl -ForegroundColor Cyan
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
$ComposerContent = '
$WorkingDir = (Split-Path -Parent $MyInvocation.MyCommand.Definition)
& ($WorkingDir + "\php.exe") (Join-Path $WorkingDir "\composer.phar") @args
'
$ComposerContent | Set-Content -Path 'runtime\composer.ps1' -Encoding UTF8

Write-Host "Successfully downloaded PHP and Composer !" -ForegroundColor Green
Write-Host "Use static-php-cli: " -NoNewline
Write-Host "bin/spc" -ForegroundColor Cyan
Write-Host "Use php:            " -NoNewline
Write-Host "runtime/php" -ForegroundColor Cyan
Write-Host "Use composer:       " -NoNewline
Write-Host "runtime/composer" -ForegroundColor Cyan
Write-Host ""
Write-Host "Don't forget installing composer dependencies '" -NoNewline
Write-Host "runtime/composer install" -ForegroundColor Cyan -NoNewline
Write-Host "' before using static-php-cli !"
Write-Host ""
Write-Host "If you want to use this PHP for quality tools (like phpstan, php-cs-fixer) or other project,"
Write-Host "or use PHP, Composer as system executable,"
Write-Host "use '" -NoNewline
Write-Host "bin/setup-runtime add-path" -ForegroundColor Cyan -NoNewline
Write-Host "' to add runtime dir in Path."
