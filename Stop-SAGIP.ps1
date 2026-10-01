#requires -Version 5.1
$ErrorActionPreference = "SilentlyContinue"

Write-Host "`n==============================================" -ForegroundColor Cyan
Write-Host "       BARANGAY SAGIP - LOCAL SHUTDOWN" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

Write-Host "[1/3] Stopping Vite..." -ForegroundColor Yellow
Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq "node.exe" -and $_.CommandLine -like "*vite*"
} | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Write-Host "[2/3] Stopping FastAPI..." -ForegroundColor Yellow
Get-CimInstance Win32_Process | Where-Object {
    ($_.Name -eq "python.exe" -or $_.Name -eq "python3.exe") -and $_.CommandLine -like "*uvicorn*main:app*"
} | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Write-Host "[3/3] Stopping Laravel queue worker..." -ForegroundColor Yellow
Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq "php.exe" -and $_.CommandLine -like "*artisan queue:work*"
} | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Write-Host "`n==============================================" -ForegroundColor Green
Write-Host "       BARANGAY SAGIP DEVELOPMENT STOPPED" -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green
Write-Host "The database service (if any) was left running."
Write-Host "Herd was left running."
