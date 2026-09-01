param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$FromVersion = "",
    [string]$ToVersion = "",
    [string]$PackageSha256 = "",
    [switch]$Force
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$FromVersion = $FromVersion.Trim('"')
$ToVersion = $ToVersion.Trim('"')
$PackageSha256 = $PackageSha256.Trim('"')

function Write-BackupLog($Message) {
    $line = "[$(Get-Date -Format o)] $Message"
    Write-Host $line
    if ($script:BackupDir) {
        $line | Add-Content -Path (Join-Path $script:BackupDir "backup.log")
    }
}

function Copy-IfExists($Source, $Destination) {
    if (Test-Path -LiteralPath $Source) {
        Copy-Item -LiteralPath $Source -Destination $Destination -Force
        Write-BackupLog "Copied: $Source"
        return $true
    }

    Write-BackupLog "Not found, skipped: $Source"
    return $false
}

function Remove-NestedManagedBackups($BackupDir) {
    $nestedBackupDir = Join-Path $BackupDir "storage-app\private\backups"
    if (Test-Path -LiteralPath $nestedBackupDir) {
        Remove-Item -LiteralPath $nestedBackupDir -Recurse -Force
        Write-BackupLog "Excluded nested managed backups from the full snapshot: $nestedBackupDir"
    }
}

function Remove-ExpiredFullBackups($BackupRoot, $CurrentBackupDir, [int]$Keep = 2) {
    if ($Keep -lt 1 -or -not (Test-Path -LiteralPath $BackupRoot)) {
        return
    }

    $resolvedRoot = (Resolve-Path -LiteralPath $BackupRoot).Path.TrimEnd('\')
    $resolvedCurrent = (Resolve-Path -LiteralPath $CurrentBackupDir).Path
    if (-not $resolvedCurrent.StartsWith($resolvedRoot + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Backup retention refused an unsafe current backup path: $resolvedCurrent"
    }

    $successfulBackups = @(Get-ChildItem -LiteralPath $resolvedRoot -Directory |
        Where-Object {
            $_.Name -match '^backup_\d{8}_\d{6}$' -and
            (Test-Path -LiteralPath (Join-Path $_.FullName 'backup-metadata.json'))
        } |
        Sort-Object Name -Descending)

    foreach ($expired in @($successfulBackups | Select-Object -Skip $Keep)) {
        $resolvedExpired = $expired.FullName
        if (-not $resolvedExpired.StartsWith($resolvedRoot + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
            throw "Backup retention refused an unsafe expired backup path: $resolvedExpired"
        }

        Write-BackupLog "Removing expired full backup: $resolvedExpired"
        Remove-Item -LiteralPath $resolvedExpired -Recurse -Force
    }
}

function Get-InstalledManifestVersion($Path) {
    try {
        if (Test-Path -LiteralPath $Path) {
            $manifest = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
            return [string]$manifest.version
        }
    } catch {
        Write-BackupLog "Could not read installed manifest version: $($_.Exception.Message)"
    }

    return ""
}

function Get-AppContainerId($InstallDir) {
    Push-Location $InstallDir
    try {
        $containerId = (& docker compose ps -q app 2>$null)
        if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($containerId)) {
            return $containerId.Trim()
        }
    } finally {
        Pop-Location
    }

    return ""
}

function Normalize-ContainerPath($Path) {
    if ($null -eq $Path) {
        return ""
    }

    return ([string]$Path).Replace("`r", "").Replace("`n", "").Trim().Trim('"').Trim("'")
}

function ConvertTo-SafeText($Value) {
    if ($null -eq $Value) {
        return ""
    }

    if ($Value -is [array]) {
        return (($Value | ForEach-Object {
            if ($null -eq $_) { "" } else { [string]$_ }
        }) -join "`n").Trim()
    }

    return ([string]$Value).Trim()
}

function Invoke-ComposeExec($InstallDir, [string[]]$Arguments) {
    Push-Location $InstallDir
    try {
        $stderrFile = [System.IO.Path]::GetTempFileName()

        try {
            $output = & docker compose exec -T app @Arguments 2> $stderrFile
            $exitCode = $LASTEXITCODE

            $stderr = ""
            if (Test-Path -LiteralPath $stderrFile) {
                $stderr = Get-Content -LiteralPath $stderrFile -Raw -ErrorAction SilentlyContinue
            }

            return [ordered]@{
                exit_code = $exitCode
                stdout = ConvertTo-SafeText $output
                stderr = ConvertTo-SafeText $stderr
            }
        }
        finally {
            Remove-Item -LiteralPath $stderrFile -Force -ErrorAction SilentlyContinue
        }
    }
    finally {
        Pop-Location
    }
}

function Get-ContainerSqlitePath($InstallDir) {
    $checkedPaths = New-Object System.Collections.Generic.List[string]

    $laravelCommand = @'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
echo config('database.connections.sqlite.database');
'@

    $result = Invoke-ComposeExec $InstallDir @("php", "-r", $laravelCommand)
    Write-BackupLog "Laravel SQLite path command exit code: $($result.exit_code)"

    if (-not [string]::IsNullOrWhiteSpace($result.stdout)) {
        Write-BackupLog "Laravel SQLite path stdout: $($result.stdout)"
    }
    if (-not [string]::IsNullOrWhiteSpace($result.stderr)) {
        Write-BackupLog "Laravel SQLite path stderr: $($result.stderr)"
    }

    $candidate = Normalize-ContainerPath $result.stdout

    if (
        $result.exit_code -eq 0 -and
        -not [string]::IsNullOrWhiteSpace($candidate) -and
        $candidate -notmatch "Error|Exception|Undefined constant|Fatal|Parse error"
    ) {
        $checkedPaths.Add($candidate)
    } else {
        Write-BackupLog "Laravel SQLite path detection did not return a usable path; fallback paths will be checked."
    }

    $fallbackPaths = @(
        "/var/www/html/database/database.sqlite",
        "/var/www/html/database/garmentsos.sqlite",
        "/var/www/html/storage/app/database.sqlite",
        "/var/www/html/storage/app/db.sqlite",
        "/var/www/html/storage/database.sqlite"
    )

    foreach ($path in $fallbackPaths) {
        if (-not $checkedPaths.Contains($path)) {
            $checkedPaths.Add($path)
        }
    }

    foreach ($path in $checkedPaths) {
        $safePath = Normalize-ContainerPath $path
        if ([string]::IsNullOrWhiteSpace($safePath)) {
            continue
        }

        Write-BackupLog "Checking SQLite path: [$safePath]"

        $checkCommand = 'DB_PATH="$1"; test -f "$DB_PATH" && ls -lah "$DB_PATH"'
        $check = Invoke-ComposeExec $InstallDir @("sh", "-lc", $checkCommand, "sh", $safePath)

        Write-BackupLog "SQLite path check exit code: $($check.exit_code)"
        if (-not [string]::IsNullOrWhiteSpace($check.stdout)) {
            Write-BackupLog "SQLite path check stdout: $($check.stdout)"
        }
        if (-not [string]::IsNullOrWhiteSpace($check.stderr)) {
            Write-BackupLog "SQLite path check stderr: $($check.stderr)"
        }

        if ($check.exit_code -eq 0) {
            Write-BackupLog "Detected SQLite path: [$safePath]"
            return $safePath
        }
    }

    throw "SQLite database not found. Checked paths: $($checkedPaths -join ', ')"
}

function Test-ContainerDbPath($InstallDir, $DbPath) {
    $checkScript = 'DB_PATH="$1"; test -f "$DB_PATH" && ls -lah "$DB_PATH"'
    $result = Invoke-ComposeExec $InstallDir @("sh", "-lc", $checkScript, "sh", $DbPath)

    Write-BackupLog "Checked SQLite path: [$DbPath]"
    Write-BackupLog "SQLite test -f exit code: $($result.exit_code)"
    if (-not [string]::IsNullOrWhiteSpace($result.stdout)) {
        Write-BackupLog "SQLite test stdout: $($result.stdout)"
    }
    if (-not [string]::IsNullOrWhiteSpace($result.stderr)) {
        Write-BackupLog "SQLite test stderr: $($result.stderr)"
    }

    if ($result.exit_code -eq 0) {
        return $true
    }

    $statScript = 'DB_PATH="$1"; stat "$DB_PATH"'
    $stat = Invoke-ComposeExec $InstallDir @("sh", "-lc", $statScript, "sh", $DbPath)

    Write-BackupLog "SQLite stat exit code: $($stat.exit_code)"
    if (-not [string]::IsNullOrWhiteSpace($stat.stdout)) {
        Write-BackupLog "SQLite stat stdout: $($stat.stdout)"
    }
    if (-not [string]::IsNullOrWhiteSpace($stat.stderr)) {
        Write-BackupLog "SQLite stat stderr: $($stat.stderr)"
    }

    return $stat.exit_code -eq 0
}

function Backup-DatabaseFromRunningContainer($InstallDir, $BackupDir) {
    $containerId = Get-AppContainerId $InstallDir
    if ([string]::IsNullOrWhiteSpace($containerId)) {
        throw "App container is not running; cannot create safe SQLite backup."
    }

    $containerBackupPath = "/tmp/garmentsos-backup.sqlite"
    $containerDbPathMarker = "/tmp/garmentsos-database-path.txt"
    $hostBackupPath = Join-Path $BackupDir "database.sqlite"

    Push-Location $InstallDir
    try {
        $dbPath = Normalize-ContainerPath (Get-ContainerSqlitePath $InstallDir)
        Write-BackupLog "Detected SQLite path: [$dbPath]"

        if (-not (Test-ContainerDbPath $InstallDir $dbPath)) {
            throw "SQLite database not found after test/stat checks: [$dbPath]"
        }

        $sqliteResult = Invoke-ComposeExec $InstallDir @("sh", "-lc", "command -v sqlite3")
        Write-BackupLog "sqlite3 path exit code: $($sqliteResult.exit_code)"
        if (-not [string]::IsNullOrWhiteSpace($sqliteResult.stdout)) {
            Write-BackupLog "sqlite3 path: $($sqliteResult.stdout)"
        }
        if (-not [string]::IsNullOrWhiteSpace($sqliteResult.stderr)) {
            Write-BackupLog "sqlite3 stderr: $($sqliteResult.stderr)"
        }
        if ($sqliteResult.exit_code -ne 0 -or [string]::IsNullOrWhiteSpace($sqliteResult.stdout)) {
            throw "sqlite3 was not found in the app container."
        }

        $cleanupResult = Invoke-ComposeExec $InstallDir @("rm", "-f", $containerBackupPath, $containerDbPathMarker)
        Write-BackupLog "SQLite temp cleanup exit code: $($cleanupResult.exit_code)"
        if (-not [string]::IsNullOrWhiteSpace($cleanupResult.stderr)) {
            Write-BackupLog "SQLite temp cleanup stderr: $($cleanupResult.stderr)"
        }

        $backupResult = Invoke-ComposeExec $InstallDir @("sqlite3", $dbPath, ".backup $containerBackupPath")
        Write-BackupLog "SQLite .backup exit code: $($backupResult.exit_code)"
        if (-not [string]::IsNullOrWhiteSpace($backupResult.stdout)) {
            Write-BackupLog "SQLite .backup stdout: $($backupResult.stdout)"
        }
        if (-not [string]::IsNullOrWhiteSpace($backupResult.stderr)) {
            Write-BackupLog "SQLite .backup stderr: $($backupResult.stderr)"
        }
        if ($backupResult.exit_code -ne 0) {
            throw "Container SQLite backup command failed with exit code $($backupResult.exit_code)."
        }

        $verifyBackupResult = Invoke-ComposeExec $InstallDir @("sh", "-lc", "test -s /tmp/garmentsos-backup.sqlite && ls -lah /tmp/garmentsos-backup.sqlite")
        Write-BackupLog "SQLite backup verify exit code: $($verifyBackupResult.exit_code)"
        if (-not [string]::IsNullOrWhiteSpace($verifyBackupResult.stdout)) {
            Write-BackupLog "SQLite backup verify stdout: $($verifyBackupResult.stdout)"
        }
        if (-not [string]::IsNullOrWhiteSpace($verifyBackupResult.stderr)) {
            Write-BackupLog "SQLite backup verify stderr: $($verifyBackupResult.stderr)"
        }
        if ($verifyBackupResult.exit_code -ne 0) {
            throw "SQLite backup file was not created in container."
        }

        $pathWriteResult = Invoke-ComposeExec $InstallDir @("sh", "-lc", 'printf "%s" "$1" > /tmp/garmentsos-database-path.txt', "sh", $dbPath)
        Write-BackupLog "SQLite path marker write exit code: $($pathWriteResult.exit_code)"
        if (-not [string]::IsNullOrWhiteSpace($pathWriteResult.stderr)) {
            Write-BackupLog "SQLite path marker stderr: $($pathWriteResult.stderr)"
        }
        if ($pathWriteResult.exit_code -ne 0) {
            throw "SQLite path marker could not be written in container."
        }

        docker cp "${containerId}:${containerBackupPath}" $hostBackupPath
        if ($LASTEXITCODE -ne 0) {
            throw "docker cp failed while copying database backup."
        }

        $dbPathFile = Join-Path $BackupDir "database-path.txt"
        docker cp "${containerId}:${containerDbPathMarker}" $dbPathFile 2>$null | Out-Null

        if (-not (Test-Path -LiteralPath $hostBackupPath)) {
            throw "Database backup file was not created."
        }

        $dbSize = (Get-Item -LiteralPath $hostBackupPath).Length
        if ($dbSize -le 0) {
            throw "Database backup file is empty."
        }

        Write-BackupLog "Database backup size: $dbSize bytes"
        Write-BackupLog "SQLite database backup created: $hostBackupPath ($dbSize bytes)"

        return @{
            ok = $true
            path = $hostBackupPath
            size = $dbSize
            container_database_path = if (Test-Path -LiteralPath $dbPathFile) {
                (Get-Content -LiteralPath $dbPathFile -Raw).Trim()
            } else {
                ""
            }
        }
    }
    finally {
        try {
            & docker compose exec -T app rm -f $containerBackupPath $containerDbPathMarker 2>$null | Out-Null
        } catch {
        }

        Pop-Location
    }
}

function Copy-ContainerPath($InstallDir, $ContainerId, $ContainerPath, $TargetPath) {
    try {
        & docker compose exec -T app sh -lc 'CONTAINER_PATH="$1"; test -e "$CONTAINER_PATH"' sh $ContainerPath
        if ($LASTEXITCODE -ne 0) {
            Write-BackupLog "Container path not found, skipped: $ContainerPath"
            return $false
        }

        docker cp "${ContainerId}:${ContainerPath}" $TargetPath
        if ($LASTEXITCODE -eq 0) {
            Write-BackupLog "Copied container path: $ContainerPath"
            return $true
        }
    } catch {
        Write-BackupLog "Container path copy failed for ${ContainerPath}: $($_.Exception.Message)"
    }

    return $false
}

if (-not (Test-Path -LiteralPath $InstallDir)) {
    throw "Install directory not found: $InstallDir"
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is required."
}

docker info | Out-Null

$BackupRoot = Join-Path $InstallDir "backups"
$Stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$script:BackupDir = Join-Path $BackupRoot "backup_$Stamp"
New-Item -ItemType Directory -Force -Path $script:BackupDir | Out-Null

Write-BackupLog "Starting GarmentsOS PRO full backup."
Write-BackupLog "InstallDir: $InstallDir"
Write-BackupLog "BackupDir: $script:BackupDir"

if ([string]::IsNullOrWhiteSpace($FromVersion)) {
    $FromVersion = Get-InstalledManifestVersion (Join-Path $InstallDir "manifest.json")
}

$backupStatus = "started"
$databaseBackup = $null
$containerId = ""
$envCopied = $false
$composeCopied = $false
$manifestSource = Join-Path $InstallDir "manifest.json"
$manifestRequired = Test-Path -LiteralPath $manifestSource
$manifestCopied = -not $manifestRequired

try {
    $envCopied = Copy-IfExists (Join-Path $InstallDir ".env") (Join-Path $script:BackupDir ".env")
    $manifestCopied = Copy-IfExists $manifestSource (Join-Path $script:BackupDir "manifest.json")
    if (-not $manifestRequired) {
        $manifestCopied = $true
    }
    $composeCopied = Copy-IfExists (Join-Path $InstallDir "docker-compose.yml") (Join-Path $script:BackupDir "docker-compose.yml")

    $databaseBackup = Backup-DatabaseFromRunningContainer $InstallDir $script:BackupDir
    $dbBackupPath = Join-Path $script:BackupDir "database.sqlite"
    $dbBackupValid = (Test-Path -LiteralPath $dbBackupPath) -and ((Get-Item -LiteralPath $dbBackupPath).Length -gt 0)

    if (-not $envCopied) {
        throw "Backup is incomplete: .env was not copied."
    }
    if (-not $composeCopied) {
        throw "Backup is incomplete: docker-compose.yml was not copied."
    }
    if (-not $manifestCopied) {
        throw "Backup is incomplete: manifest.json was not copied."
    }
    if (-not $dbBackupValid) {
        throw "Backup is incomplete: database.sqlite is missing or empty."
    }

    $containerId = Get-AppContainerId $InstallDir
    if (-not [string]::IsNullOrWhiteSpace($containerId)) {
        Copy-ContainerPath $InstallDir $containerId "/var/www/html/storage/app" (Join-Path $script:BackupDir "storage-app") | Out-Null
        Copy-ContainerPath $InstallDir $containerId "/var/www/html/public/uploads" (Join-Path $script:BackupDir "public-uploads") | Out-Null
        Remove-NestedManagedBackups $script:BackupDir
    }

    $backupStatus = "success"
} catch {
    $backupStatus = "failed"
    Write-BackupLog "Backup failed: $($_.Exception.Message)"
    if (-not $Force) {
        throw
    }
}

if ($backupStatus -ne "success") {
    Write-BackupLog "Backup metadata was not written because backup did not complete successfully."
    if (-not $Force) {
        throw "Backup did not complete successfully."
    }
    exit 1
}

$metadata = [ordered]@{
    from_version = $FromVersion
    to_version = $ToVersion
    app_version = $FromVersion
    timestamp = (Get-Date).ToUniversalTime().ToString("o")
    package_sha256 = $PackageSha256
    database_path = if ($databaseBackup) { $databaseBackup.container_database_path } else { "" }
    database_backup_file = if ($databaseBackup) { $databaseBackup.path } else { "" }
    backup_status = $backupStatus
    backup_path = $script:BackupDir
    install_dir = $InstallDir
    database_volume = "garmentsos-pro_garmentsos_database"
    storage_volume = "garmentsos-pro_garmentsos_storage"
}

$metadataPath = Join-Path $script:BackupDir "backup-metadata.json"
$metadata | ConvertTo-Json -Depth 6 | Set-Content -Path $metadataPath -Encoding UTF8

try {
    if (-not [string]::IsNullOrWhiteSpace($containerId)) {
        docker cp $metadataPath "${containerId}:/var/www/html/storage/app/private/update-backup-status.json" 2>$null | Out-Null
    }
} catch {
    Write-BackupLog "Could not copy backup status into app storage: $($_.Exception.Message)"
}

Get-ChildItem -Path $script:BackupDir -File | ForEach-Object {
    $hash = Get-FileHash $_.FullName -Algorithm SHA256
    "$($hash.Hash.ToLower())  $($_.Name)" | Add-Content (Join-Path $script:BackupDir "SHA256SUMS.txt")
}

if ($backupStatus -ne "success" -and -not $Force) {
    throw "Backup did not complete successfully."
}

Write-BackupLog "Backup created: $script:BackupDir"
Remove-ExpiredFullBackups $BackupRoot $script:BackupDir 2
