$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$targets = @(
    (Join-Path $projectRoot ".env"),
    (Join-Path $projectRoot ".env.example")
)

function Set-OrAddEnvironmentValue {
    param(
        [string] $Content,
        [string] $Key,
        [string] $Value
    )

    $pattern = "(?m)^" + [regex]::Escape($Key) + "=.*$"
    $replacement = "$Key=$Value"

    if ([regex]::IsMatch($Content, $pattern)) {
        return [regex]::Replace($Content, $pattern, $replacement)
    }

    return $Content.TrimEnd() + "`r`n$replacement`r`n"
}

foreach ($target in $targets) {
    if (-not (Test-Path $target)) {
        Write-Warning "File tidak ditemukan: $target"
        continue
    }

    $content = Get-Content -Path $target -Raw

    $content = Set-OrAddEnvironmentValue `
        -Content $content `
        -Key "APP_LOCALE" `
        -Value "id"

    $content = Set-OrAddEnvironmentValue `
        -Content $content `
        -Key "APP_FALLBACK_LOCALE" `
        -Value "id"

    $content = Set-OrAddEnvironmentValue `
        -Content $content `
        -Key "APP_FAKER_LOCALE" `
        -Value "id_ID"

    Set-Content -Path $target -Value $content -Encoding UTF8

    Write-Host "Lokalisasi diperbarui: $target" -ForegroundColor Green
}

Write-Host ""
Write-Host "Selesai. Jalankan:" -ForegroundColor Cyan
Write-Host "php artisan optimize:clear"
Write-Host "php artisan test"
