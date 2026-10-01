#requires -Version 5.1
$ErrorActionPreference = "Stop"

$ProjectRoot = $PSScriptRoot
$LaravelRoot = Join-Path $ProjectRoot "barangay-sagip-web"
$FastApiRoot = Join-Path $ProjectRoot "tokenization-service"
$VenvPython = Join-Path $FastApiRoot ".venv\Scripts\python.exe"
$LaravelEnv = Join-Path $LaravelRoot ".env"

function Fail($Message) {
    Write-Host "`n[ERROR] $Message`n" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $LaravelRoot)) { Fail "Laravel project not found: $LaravelRoot" }
if (-not (Test-Path $FastApiRoot)) { Fail "FastAPI project not found: $FastApiRoot" }
if (-not (Test-Path $VenvPython)) { Fail "Python virtual environment not found: $VenvPython" }
if (-not (Test-Path $LaravelEnv)) { Fail "Laravel .env not found. Copy barangay-sagip-web/.env.example to .env and configure it first." }

# Read the local-only FastAPI service key from Laravel's ignored .env file.
# Never store the key in this repository or in this script.
$serviceKeyLine = Get-Content -LiteralPath $LaravelEnv | Where-Object { $_ -match '^TOKENIZATION_SERVICE_KEY\s*=' } | Select-Object -First 1
if (-not $serviceKeyLine) { Fail "TOKENIZATION_SERVICE_KEY is missing from barangay-sagip-web/.env" }

$serviceKey = ($serviceKeyLine -split '=', 2)[1].Trim().Trim('"').Trim("'")
if ([string]::IsNullOrWhiteSpace($serviceKey)) { Fail "TOKENIZATION_SERVICE_KEY is empty in barangay-sagip-web/.env" }

function Read-OptionalEnvValue($Name, $DefaultValue) {
    $line = Get-Content -LiteralPath $LaravelEnv | Where-Object { $_ -match "^$Name\s*=" } | Select-Object -First 1
    if (-not $line) { return $DefaultValue }
    $value = ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
    if ([string]::IsNullOrWhiteSpace($value)) { return $DefaultValue }
    return $value
}

$confidenceThreshold = Read-OptionalEnvValue "TOKENIZATION_CONFIDENCE_THRESHOLD" "0.45"
$assignmentMinScore = Read-OptionalEnvValue "TOKENIZATION_ASSIGNMENT_MIN_SCORE" "0.35"
$assignmentMinMargin = Read-OptionalEnvValue "TOKENIZATION_ASSIGNMENT_MIN_MARGIN" "0.05"
$rulesetVersion = Read-OptionalEnvValue "TOKENIZATION_RULESET_VERSION" "1.0.0"

Write-Host "`n==============================================" -ForegroundColor Cyan
Write-Host "       BARANGAY SAGIP - LOCAL STARTUP" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

# The app requires PHP 8.4+. Another PHP (e.g. Laragon's) may come first on
# PATH, so prefer Herd's PHP 8.4 binary for the queue worker when present.
$Php = "php"
$HerdPhp = Join-Path $env:USERPROFILE ".config\herd\bin\php84\php.exe"
if (Test-Path $HerdPhp) { $Php = $HerdPhp }
$phpVersion = & $Php -r "echo PHP_VERSION;"
if ([version]$phpVersion -lt [version]"8.4.1") { Fail "PHP 8.4.1+ is required, but '$Php' is PHP $phpVersion. Install PHP 8.4 via Herd." }

# MySQL is only needed when the app is configured to use it (SQLite needs no service).
$dbLine = Get-Content -LiteralPath $LaravelEnv | Where-Object { $_ -match '^DB_CONNECTION\s*=' } | Select-Object -First 1
$dbConnection = if ($dbLine) { ($dbLine -split '=', 2)[1].Trim().Trim('"').Trim("'") } else { "sqlite" }

if ($dbConnection -eq "mysql") {
    $mysqlService = Get-Service -Name "MySQL*" -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $mysqlService) { Fail "No MySQL Windows service was found. Install MySQL or set DB_CONNECTION=sqlite in barangay-sagip-web/.env." }

    if ($mysqlService.Status -ne "Running") {
        Write-Host "[1/4] Starting $($mysqlService.Name)..." -ForegroundColor Yellow
        Start-Service -Name $mysqlService.Name
        Start-Sleep -Seconds 2
    } else {
        Write-Host "[1/4] $($mysqlService.Name) is already running." -ForegroundColor Green
    }
} else {
    Write-Host "[1/4] Database: $dbConnection (no service to start)." -ForegroundColor Green
}

Write-Host "[2/4] Starting Vite..." -ForegroundColor Yellow
$viteCommand = "Set-Location -LiteralPath '$LaravelRoot'; npm run dev"
Start-Process powershell.exe -ArgumentList @("-NoProfile","-NoExit","-Command",$viteCommand) -WindowStyle Normal

Write-Host "[3/4] Starting FastAPI..." -ForegroundColor Yellow
$fastApiCommand = "`$env:TOKENIZATION_SERVICE_KEY='$serviceKey'; `$env:TOKENIZATION_CONFIDENCE_THRESHOLD='$confidenceThreshold'; `$env:TOKENIZATION_ASSIGNMENT_MIN_SCORE='$assignmentMinScore'; `$env:TOKENIZATION_ASSIGNMENT_MIN_MARGIN='$assignmentMinMargin'; `$env:TOKENIZATION_RULESET_VERSION='$rulesetVersion'; Set-Location -LiteralPath '$FastApiRoot'; & '$VenvPython' -m uvicorn main:app --host 127.0.0.1 --port 8001"
Start-Process powershell.exe -ArgumentList @("-NoProfile","-NoExit","-Command",$fastApiCommand) -WindowStyle Normal

Write-Host "[4/4] Starting Laravel queue..." -ForegroundColor Yellow
$queueCommand = "Set-Location -LiteralPath '$LaravelRoot'; & '$Php' artisan queue:work database --sleep=3 --tries=3 --timeout=90"
Start-Process powershell.exe -ArgumentList @("-NoProfile","-NoExit","-Command",$queueCommand) -WindowStyle Normal

Write-Host "`n==============================================" -ForegroundColor Green
Write-Host "       BARANGAY SAGIP IS STARTING" -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green
Write-Host "Application : https://barangay-sagip.test"
Write-Host "Staff Login : https://barangay-sagip.test/admin/login"
Write-Host "FastAPI     : http://127.0.0.1:8001"
Write-Host "FastAPI Docs: http://127.0.0.1:8001/docs"
Write-Host "`nThree service terminals have been opened."
Write-Host "This startup window is no longer needed."
Write-Host ""
Start-Process "https://barangay-sagip.test"
exit 0
