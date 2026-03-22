$PHP_Exec = ".\runtime\php.exe"

if (-not(Test-Path $PHP_Exec)) {
    $PHP_Exec = Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Definition
    if (-not $PHP_Exec) {
        Write-Host "Error: PHP not found, you need to install PHP on your system or use 'bin/setup-runtime'." -ForegroundColor Red
        exit 1
    }
}

& "$PHP_Exec" -d xdebug.mode=debug -d xdebug.client_host=127.0.0.1 -d xdebug.client_port=9003 -d xdebug.start_with_request=yes ("bin/spc") @args
exit $LASTEXITCODE
