$PHP_Exec = "runtime\php.exe"

if (-not(Test-Path $PHP_Exec)) {
    $PHP_Exec = Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Definition
    if (-not $PHP_Exec) {
        Write-Host "Error: PHP not found, you need to install PHP on your system or use 'bin/setup-runtime'." -ForegroundColor Red
        exit 1
    }
}

$phpArgs = "bin\spc " + $args
Start-Process $PHP_Exec -ArgumentList $phpArgs -NoNewWindow -Wait
