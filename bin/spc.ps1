$PHP_Exec = "runtime\php.exe"

if (-not(Test-Path $PHP_Exec)) {
    $PHP_Exec = Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Definition
    if (-not $PHP_Exec) {
        Write-Host "Error: PHP not found." -ForegroundColor Red
        exit 1
    }
}

$phpArgs = "bin\spc " + $args
Start-Process $PHP_Exec -ArgumentList $phpArgs -NoNewWindow -Wait
