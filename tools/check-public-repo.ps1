$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host "Memeriksa keamanan repository SupplyGuard..." -ForegroundColor Cyan

$trackedEnv = git ls-files .env
if ($trackedEnv) {
    throw ".env sedang dilacak Git. Hapus dari index sebelum push."
}

$ignoredEnv = git check-ignore .env
if (-not $ignoredEnv) {
    throw ".env belum tercantum dalam aturan ignore Git."
}

$secretLines = Select-String `
    -Path .env.example `
    -Pattern '^(APP_KEY|.*API_KEY|DB_PASSWORD)=\S+' `
    -ErrorAction SilentlyContinue

if ($secretLines) {
    $secretLines | ForEach-Object { Write-Host $_ -ForegroundColor Red }
    throw ".env.example masih memiliki nilai rahasia."
}

$sqlPath = Join-Path $projectRoot "database\supplyguard.sql"
if (-not (Test-Path $sqlPath)) {
    throw "database\supplyguard.sql tidak ditemukan."
}

$sql = Get-Content $sqlPath -Raw
$emailMatches = [regex]::Matches(
    $sql,
    '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}'
)

$allowedEmails = @(
    'admin@supplyguard.test',
    'user@supplyguard.test'
)

$unexpectedEmails = $emailMatches.Value |
    Sort-Object -Unique |
    Where-Object { $_ -notin $allowedEmails }

if ($unexpectedEmails) {
    Write-Host "Email non-demo ditemukan:" -ForegroundColor Red
    $unexpectedEmails | ForEach-Object { Write-Host "- $_" }
    throw "SQL publik masih memuat email non-demo."
}

git diff --check
if ($LASTEXITCODE -ne 0) {
    throw "Git menemukan whitespace error."
}

Write-Host "Pemeriksaan selesai: repository aman untuk tahap commit." -ForegroundColor Green
