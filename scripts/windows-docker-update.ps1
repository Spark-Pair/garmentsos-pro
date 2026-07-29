param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$ReleaseDir = "",
    [bool]$HideTechnicalFiles = $true
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$ReleaseDir = $ReleaseDir.Trim('"')
$script:EnvBackupCreated = $false
$script:EnvBackupPath = ""
$script:EnvWriteStoppedCompose = $false
$script:LicenseStateBackupPath = ""
$script:LauncherUpdateStatus = "not_checked"
$script:LauncherUpdateDetail = ""

function Copy-RootLaunchers($SourceDir, $TargetDir) {
    $launchers = @(
        "Open GarmentsOS.bat",
        "Backup GarmentsOS.bat",
        "Stop GarmentsOS.bat",
        "Update GarmentsOS.bat",
        "Repair GarmentsOS Network.bat"
    )

    foreach ($launcher in $launchers) {
        $sourcePath = Join-Path $SourceDir $launcher
        $stubPath = Join-Path $SourceDir ("scripts\package-launchers\" + $launcher + ".stub")
        $targetPath = Join-Path $TargetDir $launcher

        if (Test-Path $sourcePath) {
            Copy-Item -Force $sourcePath $targetPath
        } elseif (Test-Path $stubPath) {
            Copy-Item -Force $stubPath $targetPath
        } else {
            Write-Warning "Launcher not found and was not installed: $launcher"
        }
    }
}

function Read-EnvValueFromFile($EnvPath, $Name, $DefaultValue = "") {
    try {
        if (-not (Test-Path -LiteralPath $EnvPath)) {
            return $DefaultValue
        }

        foreach ($line in Get-Content -LiteralPath $EnvPath -ErrorAction Stop) {
            $trimmed = ([string]$line).Trim()
            if ($trimmed -match ("^" + [regex]::Escape($Name) + "=(.*)$")) {
                return $Matches[1].Trim().Trim('"').Trim("'")
            }
        }
    } catch {
        Write-Warning "Could not read $Name from .env. $($_.Exception.Message)"
    }

    return $DefaultValue
}

function Ensure-GarmentsFirewallRule($InstallDir, [int]$DefaultPort = 8000) {
    $envPath = Join-Path $InstallDir ".env"
    $portValue = Read-EnvValueFromFile $envPath "APP_PORT" ([string]$DefaultPort)
    $port = 0
    if (-not [int]::TryParse($portValue, [ref]$port) -or $port -le 0) {
        $port = $DefaultPort
    }

    $ruleName = "GarmentsOS PRO $port"
    Write-Host "Ensuring Windows Firewall inbound rule for TCP port $port..."

    try {
        $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
        if ($existing) {
            $existing | Set-NetFirewallRule -Enabled True -Direction Inbound -Action Allow -Profile Any -ErrorAction Stop
            $existing | Get-NetFirewallPortFilter | Set-NetFirewallPortFilter -Protocol TCP -LocalPort $port -ErrorAction Stop
            Write-Host "Windows Firewall rule updated: $ruleName"
        } else {
            New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Action Allow -Protocol TCP -LocalPort $port -Profile Any -RemoteAddress Any -ErrorAction Stop | Out-Null
            Write-Host "Windows Firewall rule created: $ruleName"
        }
    } catch {
        Write-Warning "LAN access may be blocked by Windows Firewall. Run 'Repair GarmentsOS Network.bat' as administrator or allow inbound TCP port $port. $($_.Exception.Message)"
    }

    try {
        $profile = Get-NetConnectionProfile -ErrorAction SilentlyContinue |
            Where-Object { $_.NetworkCategory -eq 'Public' } |
            Select-Object -First 1

        if ($profile) {
            Set-NetConnectionProfile -InterfaceIndex $profile.InterfaceIndex -NetworkCategory Private -ErrorAction Stop
            Write-Host "Windows network profile set to Private for LAN access."
        }
    } catch {
        Write-Warning "Could not set Windows network profile to Private. LAN access may still require firewall confirmation. $($_.Exception.Message)"
    }
}

function New-GarmentsShortcut($ShortcutPath, $TargetPath, $WorkingDirectory, $Arguments = "", $Description = "Open GarmentsOS PRO", $IconPath = "") {
    try {
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $ShortcutPath) | Out-Null
        $shell = New-Object -ComObject WScript.Shell
        $shortcut = $shell.CreateShortcut($ShortcutPath)
        $shortcut.TargetPath = $TargetPath
        $shortcut.Arguments = $Arguments
        $shortcut.WorkingDirectory = $WorkingDirectory
        $shortcut.Description = $Description
        $iconSourcePath = if ([string]::IsNullOrWhiteSpace($IconPath)) { "" } else { ($IconPath -replace ',\d+$', '') }
        if (-not [string]::IsNullOrWhiteSpace($IconPath) -and (Test-Path -LiteralPath $iconSourcePath)) {
            $shortcut.IconLocation = $IconPath
        }
        $shortcut.Save()
        Write-Host "Shortcut target: $TargetPath $Arguments"
        Write-Host "Shortcut created: $ShortcutPath"
    } catch {
        Write-Warning "Shortcut creation failed: $ShortcutPath. $($_.Exception.Message)"
    }
}

function Install-GarmentsShortcuts($TargetDir) {
    $setupLauncher = Join-Path $TargetDir "GarmentsOS-PRO.exe"

    if (-not (Test-Path -LiteralPath $setupLauncher)) {
        Write-Warning "GarmentsOS-PRO.exe was not found. Shortcuts were not created."
        return
    }

    $shortcutTarget = $setupLauncher
    $openArguments = "garmentsos://open"
    $iconPath = "$setupLauncher,0"

    try {
        $desktop = [Environment]::GetFolderPath("Desktop")
        if (-not [string]::IsNullOrWhiteSpace($desktop)) {
            New-GarmentsShortcut (Join-Path $desktop "GarmentsOS PRO.lnk") $shortcutTarget $TargetDir $openArguments "Open GarmentsOS PRO" $iconPath
            Write-Host "Desktop shortcut created: $(Join-Path $desktop "GarmentsOS PRO.lnk")"
        }
    } catch {
        Write-Warning "Could not resolve Desktop folder. $($_.Exception.Message)"
    }

    try {
        $programs = [Environment]::GetFolderPath("Programs")
        if (-not [string]::IsNullOrWhiteSpace($programs)) {
            $startMenuFolder = Join-Path $programs "SparkPair\GarmentsOS PRO"
            New-GarmentsShortcut (Join-Path $startMenuFolder "GarmentsOS PRO.lnk") $shortcutTarget $TargetDir $openArguments "Open GarmentsOS PRO" $iconPath
            Write-Host "Start Menu shortcut created: $(Join-Path $startMenuFolder "GarmentsOS PRO.lnk")"


        }
    } catch {
        Write-Warning "Could not create Start Menu shortcut. $($_.Exception.Message)"
    }
}

function Hide-GarmentsTechnicalFiles($TargetDir) {
    $items = @(
        "scripts",
        "images",
        "checksums",
        "docs",
        "docker-compose.yml",
        ".env",
        ".env.example",
        "manifest.json"
    )

    foreach ($item in $items) {
        $path = Join-Path $TargetDir $item
        if (-not (Test-Path -LiteralPath $path)) {
            continue
        }

        try {
            $target = Get-Item -LiteralPath $path -Force
            $target.Attributes = $target.Attributes -bor [System.IO.FileAttributes]::Hidden
        } catch {
            Write-Warning "Could not hide $path. $($_.Exception.Message)"
        }
    }
}

function Protect-GarmentsInstallFolder($TargetDir) {
    try {
        $currentUser = (whoami)
        if ([string]::IsNullOrWhiteSpace($currentUser)) {
            Write-Warning "Could not determine current Windows user for ACL hardening."
            return
        }

        icacls $TargetDir /inheritance:r `
            /grant:r "*S-1-5-18:(OI)(CI)F" `
            /grant:r "*S-1-5-32-544:(OI)(CI)F" `
            /grant:r "${currentUser}:(OI)(CI)F" `
            /grant:r "*S-1-5-32-545:(OI)(CI)RX" | Out-Null
        Write-Host "Install folder permissions hardened for standard users: $TargetDir"
    } catch {
        Write-Warning "Could not harden install folder permissions. $($_.Exception.Message)"
    }
}

function Resolve-GarmentsGuiLauncher($TargetDir) {
    $candidates = @(
        (Join-Path $TargetDir "GarmentsOS-PRO.exe"),
        (Join-Path $TargetDir "GarmentsOS-PRO-Setup.exe"),
        (Join-Path $TargetDir "GarmentsOS PRO Launcher.exe"),
        (Join-Path $TargetDir "launcher\GarmentsOS-PRO.exe"),
        (Join-Path $TargetDir "launcher\GarmentsOS-PRO-Setup.exe"),
        (Join-Path $TargetDir "launcher\GarmentsOS PRO Launcher.exe")
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

function Register-GarmentsProtocol($TargetDir) {
    $launcher = Resolve-GarmentsGuiLauncher $TargetDir
    if ([string]::IsNullOrWhiteSpace($launcher)) {
        Write-Warning "GarmentsOS GUI launcher was not found. garmentsos:// protocol was not registered."
        return
    }

    try {
        $baseKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos")
        $baseKey.SetValue("", "URL:GarmentsOS PRO Launcher")
        $baseKey.SetValue("URL Protocol", "")
        $baseKey.Close()

        $iconKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\DefaultIcon")
        $iconKey.SetValue("", "$launcher,0")
        $iconKey.Close()

        $commandKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\shell\open\command")
        $commandKey.SetValue("", "`"$launcher`" `"%1`"")
        $commandKey.Close()

        Write-Host "Registered garmentsos:// protocol for: $launcher"
    } catch {
        Write-Warning "Could not register garmentsos:// protocol. $($_.Exception.Message)"
    }
}

function Test-GarmentsFileLocked($Path) {
    if (-not (Test-Path -LiteralPath $Path)) {
        return $false
    }

    try {
        $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::Open, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
        $stream.Close()
        return $false
    } catch {
        return $true
    }
}

function Start-PendingLauncherReplacementHelper($MarkerPath, $TargetDir) {
    try {
        $updatesDir = Join-Path $TargetDir "updates"
        New-Item -ItemType Directory -Force -Path $updatesDir | Out-Null

        $helperPath = Join-Path $updatesDir "ApplyPendingLauncherUpdate.ps1"
        $helperScript = @'
param(
    [string]$MarkerPath
)

$ErrorActionPreference = "Stop"

function Write-PendingLauncherLog($Message) {
    try {
        $installDir = Split-Path -Parent $MarkerPath
        $logPath = Join-Path $installDir "pending-launcher-update.log"
        "[$(Get-Date -Format o)] $Message" | Add-Content -Path $logPath
    } catch {
    }
}

function Register-GarmentsProtocolFromLauncher($LauncherPath) {
    $baseKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos")
    $baseKey.SetValue("", "URL:GarmentsOS PRO Launcher")
    $baseKey.SetValue("URL Protocol", "")
    $baseKey.Close()

    $iconKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\DefaultIcon")
    $iconKey.SetValue("", "$LauncherPath,0")
    $iconKey.Close()

    $commandKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\shell\open\command")
    $commandKey.SetValue("", "`"$LauncherPath`" `"%1`"")
    $commandKey.Close()
}

function Get-LauncherProcesses {
    $names = @("GarmentsOS-PRO", "GarmentsOS-PRO-Setup", "GarmentsOS PRO Launcher")
    foreach ($name in $names) {
        Get-Process -Name $name -ErrorAction SilentlyContinue
    }
}

Write-PendingLauncherLog "helper started"
Write-PendingLauncherLog "marker path: $MarkerPath"

$deadline = (Get-Date).AddSeconds(60)
$lastError = $null

while ((Get-Date) -lt $deadline) {
    try {
        if (-not (Test-Path -LiteralPath $MarkerPath)) {
            Write-PendingLauncherLog "marker not found; nothing to replace"
            exit 0
        }

        $marker = Get-Content -LiteralPath $MarkerPath -Raw | ConvertFrom-Json
        $pendingPath = [string]$marker.pending_path
        $destinationPath = [string]$marker.destination_path

        Write-PendingLauncherLog "pending path: $pendingPath"
        Write-PendingLauncherLog "destination path: $destinationPath"

        if (-not (Test-Path -LiteralPath $pendingPath)) {
            throw "Pending launcher EXE not found: $pendingPath"
        }

        $processes = @(Get-LauncherProcesses)
        if ($processes.Count -gt 0) {
            Write-PendingLauncherLog "waiting for launcher processes: $($processes.ProcessName -join ', ')"
            Start-Sleep -Seconds 2
            continue
        }

        $destinationDir = Split-Path -Parent $destinationPath
        if (-not (Test-Path -LiteralPath $destinationDir)) {
            New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
        }

        Copy-Item -LiteralPath $pendingPath -Destination $destinationPath -Force
        Write-PendingLauncherLog "copy success"

        Remove-Item -LiteralPath $pendingPath -Force -ErrorAction SilentlyContinue
        Write-PendingLauncherLog "pending file removed"

        Remove-Item -LiteralPath $MarkerPath -Force -ErrorAction SilentlyContinue
        Write-PendingLauncherLog "marker removed"

        Register-GarmentsProtocolFromLauncher $destinationPath
        Write-PendingLauncherLog "protocol registered"
        exit 0
    } catch {
        $lastError = $_.Exception.Message
        Write-PendingLauncherLog "copy failure/retry: $lastError"
        Start-Sleep -Seconds 2
    }
}

Write-PendingLauncherLog "error: pending launcher replacement failed after 60 seconds. $lastError"
exit 1
'@

        Set-Content -Path $helperPath -Value $helperScript -Encoding UTF8
        $null = Start-Process -FilePath "powershell.exe" -ArgumentList @(
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-WindowStyle", "Hidden",
            "-File", $helperPath,
            "-MarkerPath", $MarkerPath
        ) -WindowStyle Hidden -ErrorAction SilentlyContinue -PassThru

        Write-Host "Pending launcher replacement helper started."

        return
    } catch {
        Write-Warning "Could not start pending launcher replacement helper. $($_.Exception.Message)"
    }
}

function Stage-GarmentsLauncherUpdate($SourcePath, $DestinationPath, $TargetDir) {
    $updatesDir = Join-Path $TargetDir "updates"
    New-Item -ItemType Directory -Force -Path $updatesDir | Out-Null

    $pendingPath = Join-Path $updatesDir "GarmentsOS-PRO.exe.pending"
    Copy-Item -Force -LiteralPath $SourcePath -Destination $pendingPath

    $markerPath = Join-Path $TargetDir ".pending-launcher-update.json"
    $marker = [ordered]@{
        app = "garmentsos-pro"
        reason = "launcher_locked"
        pending_path = $pendingPath
        destination_path = $DestinationPath
        protocol = "garmentsos"
        protocol_command = "`"$DestinationPath`" `"%1`""
        staged_at = (Get-Date).ToUniversalTime().ToString("o")
    }

    $marker | ConvertTo-Json -Depth 4 | Set-Content -Path $markerPath -Encoding UTF8
    Write-Warning "Launcher EXE is running; staged launcher update will be applied after updater exits."
    Write-Host "Pending launcher update: $pendingPath"
    Write-Host "Pending launcher marker: $markerPath"
    try {
        Start-PendingLauncherReplacementHelper $markerPath $TargetDir
    }
    catch {
        Write-Warning "Launcher replacement helper could not be started."
    }

    $script:LauncherUpdateStatus = "staged"
    $script:LauncherUpdateDetail = "pending_path=$pendingPath; marker=$markerPath; destination=$DestinationPath"
}

function Update-LauncherExeFromRelease($ReleaseDir, $InstallDir) {
    $sourceLauncher = Join-Path $ReleaseDir "GarmentsOS-PRO.exe"
    if (-not (Test-Path -LiteralPath $sourceLauncher)) {
        $legacySourceLauncher = Join-Path $ReleaseDir "launcher\GarmentsOS-PRO.exe"
        if (Test-Path -LiteralPath $legacySourceLauncher) {
            $sourceLauncher = $legacySourceLauncher
        }
    }
    if (-not (Test-Path -LiteralPath $sourceLauncher)) {
        $legacySourceLauncher = Join-Path $ReleaseDir "GarmentsOS-PRO-Setup.exe"
        if (Test-Path -LiteralPath $legacySourceLauncher) {
            $sourceLauncher = $legacySourceLauncher
        }
    }

    $destLauncher = Join-Path $InstallDir "GarmentsOS-PRO.exe"

    if (-not (Test-Path -LiteralPath $sourceLauncher)) {
        Write-Host "Launcher EXE not found in release package; continuing app update only."
        $script:LauncherUpdateStatus = "not_in_package"
        $script:LauncherUpdateDetail = "checked=$ReleaseDir"
        return
    }

    Write-Host "Launcher EXE found in release package."
    $script:LauncherUpdateStatus = "found"
    $script:LauncherUpdateDetail = "source=$sourceLauncher"
    New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null

    try {
        if (Test-GarmentsFileLocked $destLauncher) {
            Stage-GarmentsLauncherUpdate $sourceLauncher $destLauncher $InstallDir
            return
        }

        Copy-Item -Force -LiteralPath $sourceLauncher -Destination $destLauncher
        Write-Host "Launcher EXE updated."
        $script:LauncherUpdateStatus = "updated"
        $script:LauncherUpdateDetail = "source=$sourceLauncher; destination=$destLauncher"
        Register-GarmentsProtocol $InstallDir
    } catch {
        Write-Warning "Could not update launcher EXE now. $($_.Exception.Message)"
        try {
            Stage-GarmentsLauncherUpdate $sourceLauncher $destLauncher $InstallDir
        } catch {
            Write-Warning "Could not stage launcher EXE update. Continuing app update only. $($_.Exception.Message)"
            $script:LauncherUpdateStatus = "failed"
            $script:LauncherUpdateDetail = "source=$sourceLauncher; destination=$destLauncher; error=$($_.Exception.Message)"
        }
    }
}

function Set-EnvLine($Content, $Name, $Value) {
    $pattern = "(?m)^" + [regex]::Escape($Name) + "=.*$"
    $line = "$Name=$Value"
    if ($Content -match $pattern) {
        return ($Content -replace $pattern, $line)
    }

    return ($Content.TrimEnd() + "`n" + $line + "`n")
}

function Backup-EnvFile($EnvPath) {
    if ($script:EnvBackupCreated) {
        return $script:EnvBackupPath
    }

    if (-not (Test-Path -LiteralPath $EnvPath)) {
        return ""
    }

    $installRoot = Split-Path -Parent $EnvPath
    $backupDir = Join-Path $installRoot "backups"
    New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupPath = Join-Path $backupDir "env_$timestamp.env"
    Copy-Item -LiteralPath $EnvPath -Destination $backupPath -Force
    $script:EnvBackupCreated = $true
    $script:EnvBackupPath = $backupPath
    Write-Host "Environment backup created: $backupPath"
    return $backupPath
}

function Backup-GarmentsLicenseState($InstallDir) {
    $backupRoot = Join-Path $InstallDir "backups"
    New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupDir = Join-Path $backupRoot "license_state_$timestamp"
    New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
    $archivePath = Join-Path $backupDir "license-state.tgz"

    Write-Host "Backing up license/device identity state before update..."

    try {
        Push-Location $InstallDir
        try {
            $containerId = (docker compose ps -q app)
            if ([string]::IsNullOrWhiteSpace($containerId)) {
                Write-Warning "App container is not running; license state backup was skipped."
                return ""
            }

            docker compose exec -T app sh -lc 'mkdir -p /tmp/garmentsos-license-backup && cd /var/www/html/storage/app && tar -czf /tmp/garmentsos-license-state.tgz install-id.txt license 2>/tmp/garmentsos-license-tar.err; test -s /tmp/garmentsos-license-state.tgz' | Out-Host
            docker cp "${containerId}:/tmp/garmentsos-license-state.tgz" $archivePath | Out-Host
            docker compose exec -T app sh -lc 'rm -f /tmp/garmentsos-license-state.tgz /tmp/garmentsos-license-tar.err' | Out-Host

            if (-not (Test-Path -LiteralPath $archivePath) -or (Get-Item -LiteralPath $archivePath).Length -le 0) {
                Write-Warning "License state backup archive was not created."
                return ""
            }

            $metadata = [ordered]@{
                created_at = (Get-Date).ToUniversalTime().ToString("o")
                reason = "pre_update_license_state_backup"
                archive = $archivePath
                install_dir = $InstallDir
                preserved = @(
                    "storage/app/install-id.txt",
                    "storage/app/license/installation.json",
                    "storage/app/license/verify-cache.json",
                    "storage/app/license/registration-cache.json",
                    "storage/app/license/request-cache.json"
                )
            }
            $metadata | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $backupDir "license-state-metadata.json") -Encoding UTF8
            $script:LicenseStateBackupPath = $archivePath
            Write-Host "License state backup created: $archivePath"
            return $archivePath
        } finally {
            Pop-Location
        }
    } catch {
        Write-Warning "Could not backup license state before update. Existing Docker storage volume will still be preserved. $($_.Exception.Message)"
        return ""
    }
}

function Restore-GarmentsLicenseState($InstallDir, $ArchivePath) {
    if ([string]::IsNullOrWhiteSpace($ArchivePath) -or -not (Test-Path -LiteralPath $ArchivePath)) {
        Write-Warning "No license state backup archive is available to restore."
        return
    }

    Write-Warning "Restoring license/device identity state after failed update: $ArchivePath"

    try {
        Push-Location $InstallDir
        try {
            $containerId = (docker compose ps -q app)
            if ([string]::IsNullOrWhiteSpace($containerId)) {
                Write-Warning "App container is not running; attempting to start app for license state restore."
                docker compose up -d app | Out-Host

                if($LASTEXITCODE -ne 0){
                    throw "docker compose up failed."
                }
                $containerId = (docker compose ps -q app)
            }

            if ([string]::IsNullOrWhiteSpace($containerId)) {
                Write-Warning "Could not resolve app container for license state restore."
                return
            }

            docker cp $ArchivePath "${containerId}:/tmp/garmentsos-license-state.tgz" | Out-Host
            docker compose exec -T app sh -lc 'mkdir -p /var/www/html/storage/app/license && tar -xzf /tmp/garmentsos-license-state.tgz -C /var/www/html/storage/app && chown -R www-data:www-data /var/www/html/storage/app/license /var/www/html/storage/app/install-id.txt 2>/dev/null || true && chmod -R ug+rwX /var/www/html/storage/app/license /var/www/html/storage/app/install-id.txt 2>/dev/null || true && rm -f /tmp/garmentsos-license-state.tgz' | Out-Host
            Write-Warning "License/device identity state restored from update backup."
        } finally {
            Pop-Location
        }
    } catch {
        Write-Warning "Could not restore license state backup. Backup remains at: $ArchivePath. $($_.Exception.Message)"
    }
}

function Clear-EnvFileProtection($EnvPath) {
    $installRoot = Split-Path -Parent $EnvPath

    try {
        if (Test-Path -LiteralPath $installRoot) {
            attrib -R -S -H "$installRoot" 2>$null | Out-Null
        }
    } catch {
        Write-Warning "Could not clear install folder attributes before .env write. $($_.Exception.Message)"
    }

    if (-not (Test-Path -LiteralPath $EnvPath)) {
        return
    }

    try {
        $item = Get-Item -LiteralPath $EnvPath -Force
        Write-Host ".env attributes before write: $($item.Attributes)"
        try {
            $acl = Get-Acl -LiteralPath $EnvPath
            Write-Host ".env owner: $($acl.Owner)"
        } catch {
            Write-Warning "Could not read .env owner. $($_.Exception.Message)"
        }
        attrib -R -S -H "$EnvPath" 2>$null | Out-Null
        $item = Get-Item -LiteralPath $EnvPath -Force
        $removeAttributes = [System.IO.FileAttributes]::ReadOnly -bor [System.IO.FileAttributes]::Hidden -bor [System.IO.FileAttributes]::System
        $item.Attributes = $item.Attributes -band (-bnot $removeAttributes)
        $item = Get-Item -LiteralPath $EnvPath -Force
        Write-Host ".env attributes after protection clear: $($item.Attributes)"
    } catch {
        Write-Warning "Could not clear .env read-only/system/hidden attributes. $($_.Exception.Message)"
    }
}

function Test-EnvFileWritable($EnvPath) {
    try {
        if (-not (Test-Path -LiteralPath $EnvPath)) {
            return $true
        }

        $stream = [System.IO.File]::Open($EnvPath, [System.IO.FileMode]::Open, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::Read)
        $stream.Dispose()
        Write-Host ".env write test succeeded."
        return $true
    } catch {
        Write-Warning ".env write test failed: $($_.Exception.Message)"
        return $false
    }
}

function Stop-GarmentsAppForEnvWrite($EnvPath) {
    $installRoot = Split-Path -Parent $EnvPath
    if (-not (Test-Path -LiteralPath (Join-Path $installRoot "docker-compose.yml"))) {
        Write-Warning "Cannot stop Docker app for .env write because docker-compose.yml was not found."
        return
    }

    Write-Host "Stopping GarmentsOS app container before retrying .env write."
    try {
        Push-Location $installRoot
        try {
            docker compose stop app | Out-Host
            $script:EnvWriteStoppedCompose = $true
        } finally {
            Pop-Location
        }
    } catch {
        Write-Warning "Could not stop app service before .env retry. $($_.Exception.Message)"
    }
}

function Restore-EnvBackupAfterWriteFailure($EnvPath, $BackupPath) {
    if ([string]::IsNullOrWhiteSpace($BackupPath) -or -not (Test-Path -LiteralPath $BackupPath)) {
        return
    }

    try {
        Clear-EnvFileProtection $EnvPath
        Copy-Item -LiteralPath $BackupPath -Destination $EnvPath -Force
        Write-Warning "Restored .env from backup after failed write: $BackupPath"
    } catch {
        Write-Warning "Could not restore .env backup after failed write. Backup remains at: $BackupPath. $($_.Exception.Message)"
    }
}

function Write-EnvContentAtomic($EnvPath, $Content) {
    $installRoot = Split-Path -Parent $EnvPath
    New-Item -ItemType Directory -Force -Path $installRoot | Out-Null

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    $tempPath = Join-Path $installRoot (".env.tmp_" + [System.Guid]::NewGuid().ToString("N"))
    $replaceBackupPath = Join-Path $installRoot (".env.replace_backup_" + [System.Guid]::NewGuid().ToString("N"))

    try {
        [System.IO.File]::WriteAllText($tempPath, $Content, $utf8NoBom)
        if (Test-Path -LiteralPath $EnvPath) {
            [System.IO.File]::Replace($tempPath, $EnvPath, $replaceBackupPath, $true)
            Remove-Item -LiteralPath $replaceBackupPath -Force -ErrorAction SilentlyContinue
        } else {
            Move-Item -LiteralPath $tempPath -Destination $EnvPath -Force
        }
    } finally {
        Remove-Item -LiteralPath $tempPath -Force -ErrorAction SilentlyContinue
    }
}

function Save-EnvContent($EnvPath, $Content) {
    $backupPath = Backup-EnvFile $EnvPath
    Clear-EnvFileProtection $EnvPath

    if (-not (Test-EnvFileWritable $EnvPath)) {
        Stop-GarmentsAppForEnvWrite $EnvPath
        Clear-EnvFileProtection $EnvPath
    }

    try {
        Write-EnvContentAtomic $EnvPath $Content
        Write-Host ".env written atomically with UTF-8 no BOM."
    } catch {
        $firstError = $_.Exception.Message
        Write-Warning ".env atomic write failed: $firstError"
        Stop-GarmentsAppForEnvWrite $EnvPath
        Clear-EnvFileProtection $EnvPath

        try {
            Write-EnvContentAtomic $EnvPath $Content
            Write-Host ".env written atomically after stopping Docker app service."
        } catch {
            Restore-EnvBackupAfterWriteFailure $EnvPath $backupPath
            throw "Cannot write .env. Close GarmentsOS/Docker and retry, or run the updater as administrator. Last error: $($_.Exception.Message)"
        }
    }
}

function Read-EnvValue($Content, $Name) {
    $pattern = "(?m)^\s*" + [regex]::Escape($Name) + "=(.*)$"
    $match = [regex]::Match($Content, $pattern)
    if (-not $match.Success) {
        return ""
    }

    return $match.Groups[1].Value.Trim().Trim('"').Trim("'")
}

function Update-GarmentsEnvForRelease($EnvPath, $TargetVersion, $TargetImage) {
    Clear-EnvFileProtection $EnvPath
    $before = Get-Content -LiteralPath $EnvPath -Raw
    $beforeAppVersion = Read-EnvValue $before "APP_VERSION"
    $beforeImage = Read-EnvValue $before "GARMENTSOS_IMAGE"

    Write-Host "Before .env APP_VERSION: [$beforeAppVersion]"
    Write-Host "Before .env GARMENTSOS_IMAGE: [$beforeImage]"

    $updated = Set-EnvLine $before "APP_VERSION" $TargetVersion
    $updated = Set-EnvLine $updated "GARMENTSOS_IMAGE" $TargetImage
    $updated = Set-EnvLine $updated "RUN_MIGRATIONS_ON_START" "true"
    Save-EnvContent $EnvPath $updated

    $after = Get-Content -LiteralPath $EnvPath -Raw
    $afterAppVersion = Read-EnvValue $after "APP_VERSION"
    $afterImage = Read-EnvValue $after "GARMENTSOS_IMAGE"

    Write-Host "After .env APP_VERSION: [$afterAppVersion]"
    Write-Host "After .env GARMENTSOS_IMAGE: [$afterImage]"

    if ($afterAppVersion -ne $TargetVersion) {
        throw ".env APP_VERSION mismatch after update. Expected [$TargetVersion], got [$afterAppVersion]."
    }

    if ($afterImage -ne $TargetImage) {
        throw ".env GARMENTSOS_IMAGE mismatch after update. Expected [$TargetImage], got [$afterImage]."
    }

    Write-Host ".env release values verified for target version $TargetVersion."
}

function Ensure-EnvKey($EnvPath, $Key, $DefaultValue) {
    if (-not (Test-Path -LiteralPath $EnvPath)) {
        throw ".env file not found: $EnvPath"
    }

    Clear-EnvFileProtection $EnvPath
    $content = Get-Content -LiteralPath $EnvPath -Raw
    $pattern = "(?m)^\s*" + [regex]::Escape($Key) + "="
    if ($content -match $pattern) {
        Write-Host "Environment key already exists: $Key"
        return
    }

    $line = "$Key=$DefaultValue"
    $lineEnding = if ($content -match "`r`n") { "`r`n" } else { "`n" }
    $updated = $content.TrimEnd("`r", "`n") + $lineEnding + $line + $lineEnding
    Save-EnvContent $EnvPath $updated
    Write-Host "Added missing environment key: $Key"
}

function Ensure-GarmentsUpdaterEnvKeys($EnvPath) {
    Ensure-EnvKey $EnvPath "UPDATE_FEED_URL" "https://www.sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json"
    Ensure-EnvKey $EnvPath "UPDATE_FALLBACK_FEED_URL" "https://github.com/Spark-Pair/garmentsos-pro/releases/download/latest-stable/latest.json"
    Ensure-EnvKey $EnvPath "UPDATE_APP_SHELL_FEED_CACHE_SECONDS" "60"
    Ensure-EnvKey $EnvPath "UPDATE_LOCK_TTL_MINUTES" "30"
    Ensure-EnvKey $EnvPath "UPDATE_CHANNEL" "stable"
    Ensure-EnvKey $EnvPath "UPDATE_LAUNCHER_PROTOCOL" "garmentsos"
    Ensure-EnvKey $EnvPath "UPDATE_REQUEST_TTL_MINUTES" "10"
}

function Ensure-GarmentsLicenseEnvKeys($EnvPath) {
    Ensure-EnvKey $EnvPath "LICENSE_ENABLED" "true"
    Ensure-EnvKey $EnvPath "LICENSE_ENFORCEMENT_ENABLED" "true"
    Ensure-EnvKey $EnvPath "LICENSE_AUTO_REGISTER" "true"
    Ensure-EnvKey $EnvPath "LICENSE_CHECK_URL" "https://www.sparkpair.dev/api/licenses/verify"
    Ensure-EnvKey $EnvPath "LICENSE_REGISTER_URL" "https://www.sparkpair.dev/api/licenses/register-install"
    Ensure-EnvKey $EnvPath "LICENSE_REQUEST_DEMO_URL" "https://www.sparkpair.dev/api/licenses/request-demo"
    Ensure-EnvKey $EnvPath "LICENSE_DEVELOPMENT_BYPASS" "false"
    Ensure-EnvKey $EnvPath "LICENSE_GRACE_DAYS" "7"
}

function Ensure-GarmentsRuntimeDatabasePath($EnvPath) {
    $current = Read-EnvValueFromFile $EnvPath "DB_DATABASE" ""
    $normalized = $current.Trim().Trim('"').Trim("'").Replace("\", "/")
    $target = "/var/www/html/database/runtime/database.sqlite"

    if ([string]::IsNullOrWhiteSpace($normalized) -or
        $normalized -eq "/var/www/html/database/database.sqlite" -or
        $normalized -eq "database/database.sqlite") {
        $content = Get-Content -LiteralPath $EnvPath -Raw -ErrorAction Stop
        $updated = Set-EnvLine $content "DB_DATABASE" $target
        Save-EnvContent $EnvPath $updated
        Write-Host "Updated DB_DATABASE to runtime volume path so Laravel migrations remain visible: $target"
        return
    }

    Write-Host "Preserved custom DB_DATABASE path: $current"
}

function Remove-InstalledEnvTemplate($InstallDir) {
    $template = Join-Path $InstallDir ".env.example"
    try {
        if (Test-Path -LiteralPath $template) {
            Remove-Item -LiteralPath $template -Force
            Write-Host "Removed installed .env.example template from runtime root: $template"
        } else {
            Write-Host "Installed .env.example template is not present in runtime root."
        }
    } catch {
        Write-Warning "Could not remove installed .env.example template: $($_.Exception.Message)"
    }
}

function Write-UpdateLog($InstallDir, $Message) {
    try {
        $updatesDir = Join-Path $InstallDir "updates"
        New-Item -ItemType Directory -Force -Path $updatesDir | Out-Null
        $logPath = Join-Path $updatesDir "update.log"
        $line = "[$(Get-Date -Format o)] $Message"
        Add-Content -LiteralPath $logPath -Value $line -Encoding UTF8
        Write-Host $line
    } catch {
        Write-Warning "Could not append update log entry: $($_.Exception.Message)"
    }
}

function Invoke-ComposeExecSafe(
    [string]$InstallDir,
    [string]$Command,
    [string]$Label
) {

    Push-Location $InstallDir

    try {

        Write-Host "Running $Label"

        Wait-GarmentsAppExecReady $InstallDir

        $output = docker compose exec -T app sh -lc "$Command"

        $exitCode = $LASTEXITCODE

        if ($output) {
            $output | Write-Host
        }

        if ($exitCode -ne 0) {

            Write-Host ""
            Write-Host "docker returned exit code $exitCode"

            docker compose ps

            docker compose logs app --tail=100

            throw "$Label failed (docker exit $exitCode)"
        }

    }
    finally {

        Pop-Location

    }

}

function Wait-GarmentsAppExecReady($InstallDir, [int]$TimeoutSeconds = 120) {
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    Push-Location $InstallDir
    try {
        do {
            $containerId = (docker compose ps -q app 2>$null)
            if (-not [string]::IsNullOrWhiteSpace($containerId)) {
                $running = docker inspect -f "{{.State.Running}}" $containerId 2>$null
                if ($running -match "true") {
                    docker compose exec -T app sh -lc 'php -v >/dev/null 2>&1' 2>$null
                    if ($LASTEXITCODE -eq 0) {
                        Write-Host "App container is ready for Laravel commands."
                        return
                    }
                }
            }

            Start-Sleep -Seconds 3
        } while ((Get-Date) -lt $deadline)
    } finally {
        Pop-Location
    }

    throw "App container did not become ready for Laravel commands within $TimeoutSeconds seconds."
}

function Invoke-GarmentsStorageRepair($InstallDir) {
    Wait-GarmentsAppExecReady $InstallDir

#     $repairCommand = @'
# php artisan app:repair-storage --no-interaction || (
#   mkdir -p storage/app/license storage/app/private/backups storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database &&
#   chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true &&
#   chmod -R ug+rwX storage bootstrap/cache database 2>/dev/null || true
# )
# '@

    $repairCommand = @'
set -e

php artisan app:repair-storage --no-interaction

mkdir -p storage/app/license
mkdir -p storage/framework/cache/data

chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R ug+rwX storage bootstrap/cache database || true
'@

    Invoke-ComposeExecSafe $InstallDir $repairCommand 'repair storage permissions'
}

function Test-GarmentsImageContainsMigration($Image, $LatestMigration) {
    if ([string]::IsNullOrWhiteSpace($LatestMigration)) {
        Write-Host "Release manifest did not include latest_migration; skipping image migration visibility check."
        return
    }

    Write-Host "Verifying Docker image contains latest migration: $LatestMigration"
    docker run --rm --entrypoint sh $Image -lc "test -f 'database/migrations/$LatestMigration'"
    if ($LASTEXITCODE -ne 0) {
        throw "Docker image $Image does not contain expected migration: $LatestMigration"
    }
}

function Test-GarmentsRunningContainerContainsMigration($InstallDir, $LatestMigration) {
    if ([string]::IsNullOrWhiteSpace($LatestMigration)) {
        return
    }

    Write-Host "Verifying running container can see latest migration: $LatestMigration"
    Push-Location $InstallDir
    try {
        docker compose exec -T app sh -lc "test -f 'database/migrations/$LatestMigration'"
        if ($LASTEXITCODE -ne 0) {
            throw "Running app container cannot see expected migration: $LatestMigration. Check docker-compose database volume mount."
        }
    } finally {
        Pop-Location
    }
}

function Add-PostUpdateCheck($Checks, [string]$Name, [bool]$Passed, [string]$Detail = "", [int]$ExitCode = 0) {
    $Checks[$Name] = [ordered]@{
        passed = $Passed
        detail = $Detail
        exit_code = $ExitCode
    }
}

function Format-PostUpdateOutput($Output) {
    if ($null -eq $Output) {
        return ""
    }

    if ($Output -is [array]) {
        return (($Output | ForEach-Object { [string]$_ }) -join " ").Trim()
    }

    return ([string]$Output).Trim()
}

function Write-PostUpdateCheckResult($InstallDir, [string]$Name, $Check) {
    $status = if ($Check.passed) { "PASS" } else { "FAIL" }
    $detail = [string]$Check.detail
    $exitCode = [int]$Check.exit_code
    $message = "post-update check [$Name] $status"

    if ($exitCode -ne 0) {
        $message += " exit=$exitCode"
    }

    if (-not [string]::IsNullOrWhiteSpace($detail)) {
        $message += " detail=$detail"
    }

    Write-Host $message
    Write-UpdateLog $InstallDir $message
}

function Wait-GarmentsViteManifestReady($InstallDir, [int]$TimeoutSeconds = 60) {
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    $lastDetail = ""
    $lastExitCode = 0

    Push-Location $InstallDir
    try {
        do {
            $shellOutput = docker compose exec -T app sh -lc 'test -f /var/www/html/public/build/manifest.json && echo manifest-present || echo manifest-missing' 2>&1
            $shellExitCode = $LASTEXITCODE
            $phpOutput = docker compose exec -T app php -r "echo file_exists('/var/www/html/public/build/manifest.json') ? 'asset-ok' : 'asset-missing';" 2>&1
            $phpExitCode = $LASTEXITCODE

            $shellText = Format-PostUpdateOutput $shellOutput
            $phpText = Format-PostUpdateOutput $phpOutput
            $lastExitCode = if ($phpExitCode -ne 0) { $phpExitCode } else { $shellExitCode }
            $lastDetail = "test=$shellText; php=$phpText"

            if ($shellExitCode -eq 0 -and $shellText -match "manifest-present" -and $phpExitCode -eq 0 -and $phpText -match "asset-ok") {
                return [ordered]@{
                    passed = $true
                    detail = $lastDetail
                    exit_code = 0
                }
            }

            Start-Sleep -Seconds 3
        } while ((Get-Date) -lt $deadline)

        $listing = docker compose exec -T app sh -lc 'pwd; ls -lah /var/www/html/public 2>&1; ls -lah /var/www/html/public/build 2>&1 || true' 2>&1
        $listingText = Format-PostUpdateOutput $listing
        return [ordered]@{
            passed = $false
            detail = "$lastDetail; listing=$listingText"
            exit_code = $lastExitCode
        }
    } finally {
        Pop-Location
    }
}

function Verify-PostUpdateState($InstallDir, $ExpectedVersion, $ExpectedImage = "") {
    $checks = [ordered]@{}

    Push-Location $InstallDir
    try {
        $envPath = Join-Path $InstallDir ".env"
        $envContent = Get-Content -LiteralPath $envPath -Raw -ErrorAction Stop
        $appVersion = Read-EnvValue $envContent "APP_VERSION"
        Add-PostUpdateCheck $checks "app_version" ($appVersion -eq $ExpectedVersion) "expected=$ExpectedVersion; actual=$appVersion"

        $actualImage = Read-EnvValue $envContent "GARMENTSOS_IMAGE"
        if (-not [string]::IsNullOrWhiteSpace($ExpectedImage)) {
            Add-PostUpdateCheck $checks "garmentsos_image" ($actualImage -eq $ExpectedImage) "expected=$ExpectedImage; actual=$actualImage"
        } else {
            Add-PostUpdateCheck $checks "garmentsos_image" (-not [string]::IsNullOrWhiteSpace($actualImage)) "actual=$actualImage"
        }

        Wait-GarmentsAppExecReady $InstallDir
        Invoke-GarmentsStorageRepair $InstallDir

        $dockerHealthy = docker compose ps app 2>&1
        $dockerPsExit = $LASTEXITCODE
        Add-PostUpdateCheck $checks "docker_running" ($dockerPsExit -eq 0 -and (Format-PostUpdateOutput $dockerHealthy) -match "Up") (Format-PostUpdateOutput $dockerHealthy) $dockerPsExit

        $storageLink = docker compose exec -T app sh -lc 'test -L /var/www/html/public/storage && echo linked || echo missing' 2>&1
        $storageExit = $LASTEXITCODE
        Add-PostUpdateCheck $checks "storage_link" ($storageExit -eq 0 -and (Format-PostUpdateOutput $storageLink) -match "linked") (Format-PostUpdateOutput $storageLink) $storageExit

        $assetCheck = Wait-GarmentsViteManifestReady $InstallDir 60
        Add-PostUpdateCheck $checks "asset_ready" ([bool]$assetCheck.passed) ([string]$assetCheck.detail) ([int]$assetCheck.exit_code)
        Add-PostUpdateCheck $checks "manifest_present" ([bool]$assetCheck.passed) ([string]$assetCheck.detail) ([int]$assetCheck.exit_code)

        $launcherStatus = [string]$script:LauncherUpdateStatus
        $launcherOk = $launcherStatus -in @("updated", "staged")
        Add-PostUpdateCheck $checks "launcher_update" $launcherOk "status=$launcherStatus; $($script:LauncherUpdateDetail)"

        $migrationOutput = docker compose exec -T app sh -lc 'php artisan migrate --force --no-interaction' 2>&1
        $migrationExit = $LASTEXITCODE
        Add-PostUpdateCheck $checks "migrations" ($migrationExit -eq 0) (Format-PostUpdateOutput $migrationOutput) $migrationExit
    } finally {
        Pop-Location
    }

    $failed = @()

    foreach ($name in $checks.Keys) {
        $check = $checks[$name]
        Write-PostUpdateCheckResult $InstallDir $name $check

        if (-not $check.passed) {
            switch ($name) {
                "app_version" { $failed += "APP_VERSION mismatch. $($check.detail)" }
                "garmentsos_image" { $failed += "GARMENTSOS_IMAGE mismatch. $($check.detail)" }
                "docker_running" { $failed += "Docker app container did not remain healthy after restart. $($check.detail)" }
                "storage_link" { $failed += "Storage link verification failed. $($check.detail)" }
                "manifest_present" { $failed += "public/build/manifest.json verification failed. $($check.detail)" }
                "asset_ready" { $failed += "Post-update asset verification failed. $($check.detail)" }
                "launcher_update" { $failed += "Launcher update was neither completed nor safely staged. $($check.detail)" }
                "migrations" { $failed += "Migration verification failed. $($check.detail)" }
                default { $failed += "$name verification failed. $($check.detail)" }
            }
        }
    }

    if ($failed.Count -gt 0) {
        throw ($failed -join '; ')
    }

    Write-Host "Post-update verification passed for version $ExpectedVersion."
    return $checks
}

function Invoke-GarmentsLaravelMaintenance($InstallDir) {
    try {
        Push-Location $InstallDir
        try {
            Write-Host "Running Laravel maintenance commands..."
            Write-Host "Skipping composer install in client runtime; release Docker image already contains vendor/autoload."
            Invoke-GarmentsStorageRepair $InstallDir
            Invoke-ComposeExecSafe $InstallDir 'php artisan migrate --force' 'migrate'
            Write-Host "Laravel migration maintenance completed."
        } finally {
            Pop-Location
        }
    } catch {
        Write-Warning "Laravel maintenance failed. App may still run, but cache/storage repair may be needed. $($_.Exception.Message)"
        throw
    }
}

function Invoke-GarmentsPostSuccessMaintenance($InstallDir) {
    Push-Location $InstallDir
    try {
        Write-Host "Running final Laravel maintenance after successful update..."
        Invoke-GarmentsStorageRepair $InstallDir
        try {
            Invoke-ComposeExecSafe $InstallDir 'php artisan storage:link --force || php artisan storage:link' 'storage:link'
        }
        catch {
            Write-Warning "storage:link failed after successful update: $($_.Exception.Message)"
        }

        Invoke-ComposeExecSafe $InstallDir 'php artisan optimize:clear' 'optimize:clear'
        Invoke-ComposeExecSafe $InstallDir 'php artisan optimize' 'optimize'
        Write-Host "Final Laravel maintenance completed: storage:link, optimize:clear, optimize."
    } finally {
        Pop-Location
    }
}

function Restart-GarmentsAppContainer($InstallDir, $Reason) {
    Push-Location $InstallDir
    try {
        Write-Host "Restarting GarmentsOS app container: $Reason"
        docker compose restart app | Out-Host
        Wait-GarmentsAppExecReady $InstallDir
        Write-UpdateLog $InstallDir "docker compose restart app: $Reason completed"
    } finally {
        Pop-Location
    }
}

function Invoke-GarmentsFinalSuccessPass($InstallDir) {
    try {
        Invoke-GarmentsPostSuccessMaintenance $InstallDir
        Restart-GarmentsAppContainer $InstallDir "after final Laravel maintenance"
    }
    catch {
        Write-Warning "Final post-update maintenance failed after update verification. Update remains applied; run Repair if needed. $($_.Exception.Message)"
        Write-UpdateLog $InstallDir "final post-update maintenance warning: $($_.Exception.Message)"
    }
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is required."
}
try {
    docker info | Out-Null
} catch {
    throw "Docker Desktop is not running."
}

if ([string]::IsNullOrWhiteSpace($ReleaseDir)) {
    $ReleaseDir = Split-Path -Parent $PSScriptRoot
}

$Manifest = Get-Content (Join-Path $ReleaseDir "manifest.json") | ConvertFrom-Json
$ImageTar = Join-Path $ReleaseDir $Manifest.image_tar
if (-not (Test-Path $ImageTar)) {
    throw "Docker image tar not found: $ImageTar"
}

$InstalledManifestPath = Join-Path $InstallDir "manifest.json"
$FromVersion = ""
if (Test-Path -LiteralPath $InstalledManifestPath) {
    try {
        $InstalledManifest = Get-Content -LiteralPath $InstalledManifestPath -Raw | ConvertFrom-Json
        $FromVersion = [string]$InstalledManifest.version
    } catch {
        Write-Warning "Could not read installed manifest version. $($_.Exception.Message)"
    }
}

$ReleaseBackupScript = Join-Path $ReleaseDir "scripts\windows-docker-backup.ps1"
$InstalledBackupScript = Join-Path $InstallDir "scripts\windows-docker-backup.ps1"
$BackupScript = if (Test-Path $ReleaseBackupScript) {
    $ReleaseBackupScript
} else {
    $InstalledBackupScript
}

if (-not (Test-Path $BackupScript)) {
    throw "Backup script not found. Checked release and installed script paths."
}

Write-Host "Using backup script: $BackupScript"
try {
    & $BackupScript -InstallDir $InstallDir -FromVersion $FromVersion -ToVersion ([string]$Manifest.version) -PackageSha256 ([string]$Manifest.image_sha256)
} catch {
    Write-Error "Pre-update backup failed. Update was not applied. $($_.Exception.Message)"
    throw
}

$LicenseStateBackup = Backup-GarmentsLicenseState $InstallDir

try {
    docker load -i $ImageTar | Out-Host
    
    if($LASTEXITCODE -ne 0){
        throw "Docker image load failed."
    }

    Copy-Item -Force (Join-Path $ReleaseDir "docker-compose.yml") $InstallDir
    Copy-Item -Force (Join-Path $ReleaseDir ".env.example") $InstallDir
    Copy-Item -Recurse -Force (Join-Path $ReleaseDir "scripts") $InstallDir
    Copy-Item -Recurse -Force (Join-Path $ReleaseDir "docs") $InstallDir
    Copy-Item -Recurse -Force (Join-Path $ReleaseDir "images") $InstallDir
    Copy-Item -Recurse -Force (Join-Path $ReleaseDir "checksums") $InstallDir
    Copy-Item -Force (Join-Path $ReleaseDir "manifest.json") $InstallDir
    Update-LauncherExeFromRelease $ReleaseDir $InstallDir
    Copy-RootLaunchers $ReleaseDir $InstallDir

    $EnvPath = Join-Path $InstallDir ".env"
    if (-not (Test-Path $EnvPath)) {
        throw "Existing .env is required for update."
    }

    Ensure-GarmentsUpdaterEnvKeys $EnvPath
    Ensure-GarmentsLicenseEnvKeys $EnvPath
    Ensure-GarmentsRuntimeDatabasePath $EnvPath
    Ensure-GarmentsFirewallRule $InstallDir 8000

    $TargetVersion = [string]$Manifest.version
    $TargetImage = [string]$Manifest.image
    $LatestMigration = [string]$Manifest.latest_migration
    if ([string]::IsNullOrWhiteSpace($TargetImage)) {
        $TargetImage = "sparkpair/garmentsos-pro:$TargetVersion"
    }

    Test-GarmentsImageContainsMigration $TargetImage $LatestMigration

    Update-GarmentsEnvForRelease $EnvPath $TargetVersion $TargetImage

    Push-Location $InstallDir
    try {
        Write-UpdateLog $InstallDir "update start version: $TargetVersion"
        docker compose up -d

        if($LASTEXITCODE -ne 0){
            throw "docker compose up failed."
        }
        Wait-GarmentsAppExecReady $InstallDir
        Test-GarmentsRunningContainerContainsMigration $InstallDir $LatestMigration
        $script:EnvWriteStoppedCompose = $false
    } finally {
        Pop-Location
    }

    Write-UpdateLog $InstallDir "docker compose restart: performing post-update maintenance"
    Invoke-GarmentsLaravelMaintenance $InstallDir

    Push-Location $InstallDir
    try {
        docker compose restart app | Out-Host
        Wait-GarmentsAppExecReady $InstallDir
        Write-UpdateLog $InstallDir "docker compose restart app: completed"
    } finally {
        Pop-Location
    }

    $envContent = Get-Content $EnvPath -Raw
    $envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "false"
    Save-EnvContent $EnvPath $envContent

    if ($script:EnvWriteStoppedCompose) {
        Write-Host "Restarting GarmentsOS app after .env write retry stopped the app service."
        Push-Location $InstallDir
        try {
            docker compose up -d

            if($LASTEXITCODE -ne 0){
                throw "docker compose up failed."
            }
            Wait-GarmentsAppExecReady $InstallDir
            $script:EnvWriteStoppedCompose = $false
        } finally {
            Pop-Location
        }
    }

    Verify-PostUpdateState $InstallDir $TargetVersion $TargetImage
    Invoke-GarmentsFinalSuccessPass $InstallDir

    try {
        Register-GarmentsProtocol $InstallDir
    }
    catch {
        Write-Warning $_
    }

    try {
        Install-GarmentsShortcuts $InstallDir
    }
    catch {
        Write-Warning $_
    }

    Remove-InstalledEnvTemplate $InstallDir

    if ($HideTechnicalFiles) {
        Hide-GarmentsTechnicalFiles $InstallDir
    } else {
        Write-Host "Technical files were left visible because HideTechnicalFiles is false."
    }
    Protect-GarmentsInstallFolder $InstallDir

    Write-UpdateLog $InstallDir "update finish version: $TargetVersion"
    Write-Host "Update complete. Volumes were preserved."
    Write-Host "License/device identity state was preserved in the Docker storage volume."
    Write-Host "Rollback: load the previous image tar, set GARMENTSOS_IMAGE in .env to the previous tag, then run docker compose up -d."
} catch {
    Write-UpdateLog $InstallDir "rollback reason: $($_.Exception.Message)"
    Write-Warning "Update failed after pre-update backup. Attempting to preserve/restore license device identity. $($_.Exception.Message)"
    Restore-GarmentsLicenseState $InstallDir $LicenseStateBackup
    throw
}

if($LASTEXITCODE -eq 137){

    docker compose logs app --tail=200

    throw "Container terminated unexpectedly (137)."
}
