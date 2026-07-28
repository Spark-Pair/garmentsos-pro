param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$Version = "",
    [int]$Port = 8000,
    [bool]$HideTechnicalFiles = $true,
    [switch]$FreshReset
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$Version = $Version.Trim('"')
$script:EnvBackupCreated = $false
$script:ResetLogPath = $null

function Write-ResetLog($Message) {
    if ([string]::IsNullOrWhiteSpace($script:ResetLogPath)) {
        Write-Host $Message
        return
    }

    try {
        "[$(Get-Date -Format o)] $Message" | Add-Content -LiteralPath $script:ResetLogPath
    } catch {
    }

    Write-Host $Message
}

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
    $openLauncher = Join-Path $TargetDir "Open GarmentsOS.bat"
    $shortcutTarget = if (Test-Path -LiteralPath $setupLauncher) {
        $setupLauncher
    } else {
        $openLauncher
    }

    if (-not (Test-Path -LiteralPath $shortcutTarget)) {
        Write-Warning "GarmentsOS launcher was not found. Shortcuts were not created."
        return
    }

    $usesExe = (Test-Path -LiteralPath $setupLauncher)
    $openArguments = if ($usesExe) { "garmentsos://open" } else { "" }
    $iconPath = if ($usesExe) { "$setupLauncher,0" } else { "" }

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

            if ($usesExe) {
                New-GarmentsShortcut (Join-Path $startMenuFolder "GarmentsOS PRO Updater.lnk") $setupLauncher $TargetDir "" "Open GarmentsOS PRO Updater" $iconPath
                Write-Host "Start Menu shortcut created: $(Join-Path $startMenuFolder "GarmentsOS PRO Updater.lnk")"
            }

        }
    } catch {
        Write-Warning "Could not create Start Menu shortcut. $($_.Exception.Message)"
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

function Unregister-GarmentsProtocol() {
    try {
        $protocolKey = "HKCU:\Software\Classes\garmentsos"
        if (Test-Path -LiteralPath $protocolKey) {
            Remove-Item -LiteralPath $protocolKey -Recurse -Force
            Write-Host "Removed garmentsos:// protocol registration."
        } else {
            Write-Host "garmentsos:// protocol registration was not present."
        }
    } catch {
        Write-Warning "Could not remove garmentsos:// protocol registration. $($_.Exception.Message)"
    }
}

function Remove-GarmentsShortcuts() {
    $paths = @()

    try {
        $desktop = [Environment]::GetFolderPath("Desktop")
        if (-not [string]::IsNullOrWhiteSpace($desktop)) {
            $paths += (Join-Path $desktop "GarmentsOS PRO.lnk")
        }
    } catch {
        Write-Warning "Could not resolve Desktop folder while removing shortcuts. $($_.Exception.Message)"
    }

    try {
        $programs = [Environment]::GetFolderPath("Programs")
        if (-not [string]::IsNullOrWhiteSpace($programs)) {
            $paths += (Join-Path $programs "SparkPair\GarmentsOS PRO\GarmentsOS PRO.lnk")
            $paths += (Join-Path $programs "SparkPair\GarmentsOS PRO\GarmentsOS PRO Updater.lnk")
            $paths += (Join-Path $programs "SparkPair\GarmentsOS PRO\Open Install Folder.lnk")
        }
    } catch {
        Write-Warning "Could not resolve Start Menu folder while removing shortcuts. $($_.Exception.Message)"
    }

    foreach ($path in $paths) {
        try {
            if (Test-Path -LiteralPath $path) {
                Remove-Item -LiteralPath $path -Force
                Write-Host "Removed shortcut: $path"
            }
        } catch {
            Write-Warning "Could not remove shortcut: $path. $($_.Exception.Message)"
        }
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

function Repair-GarmentsInstallFolderAccess($TargetDir) {
    Write-Host "Repairing install folder permissions before .env handling: $TargetDir"
    try {
        New-Item -ItemType Directory -Force -Path $TargetDir | Out-Null
        $currentUser = (whoami)
        if (-not [string]::IsNullOrWhiteSpace($currentUser)) {
            icacls $TargetDir /inheritance:e `
                /grant "*S-1-5-18:(OI)(CI)F" `
                /grant "*S-1-5-32-544:(OI)(CI)F" `
                /grant "${currentUser}:(OI)(CI)F" | Out-Null
        }
        Write-Host "Install folder permission repair completed."
    } catch {
        Write-Warning "Install folder permission repair warning: $($_.Exception.Message)"
    }
}

function Clear-GarmentsFileAttributes($Path) {
    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    try {
        Get-ChildItem -LiteralPath $Path -Force -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
            try {
                $_.Attributes = $_.Attributes -band (-bnot [System.IO.FileAttributes]::ReadOnly)
                $_.Attributes = $_.Attributes -band (-bnot [System.IO.FileAttributes]::Hidden)
                $_.Attributes = $_.Attributes -band (-bnot [System.IO.FileAttributes]::System)
            } catch {
                Write-Warning "Could not clear attributes: $($_.FullName). $($_.Exception.Message)"
            }
        }
        $item = Get-Item -LiteralPath $Path -Force
        $item.Attributes = $item.Attributes -band (-bnot [System.IO.FileAttributes]::ReadOnly)
        $item.Attributes = $item.Attributes -band (-bnot [System.IO.FileAttributes]::Hidden)
        $item.Attributes = $item.Attributes -band (-bnot [System.IO.FileAttributes]::System)
        Write-Host "Cleared readonly/hidden/system attributes under: $Path"
    } catch {
        Write-Warning "Could not clear attributes under $Path. $($_.Exception.Message)"
    }
}

function Remove-GarmentsDockerState($TargetDir) {
    Write-Host "Reset mode selected: stopping and removing Docker containers/volumes."
    try {
        if (Test-Path -LiteralPath (Join-Path $TargetDir "docker-compose.yml")) {
            Push-Location $TargetDir
            try {
                docker compose down --volumes --remove-orphans | Out-Host
            } finally {
                Pop-Location
            }
        } else {
            Write-Host "docker-compose.yml not found in install folder; removing known GarmentsOS Docker resources."
        }
    } catch {
        Write-Warning "Docker compose reset warning: $($_.Exception.Message)"
    }

    foreach ($container in @("garmentsos-pro-app-1", "garmentsos-pro-app", "garmentsos-app", "garmentsos-app-1")) {
        try {
            docker rm -f $container 2>$null | Out-Null
            Write-Host "Removed Docker container if present: $container"
        } catch {
        }
    }

    foreach ($volume in @("garmentsos-pro_garmentsos_database", "garmentsos-pro_garmentsos_storage", "garmentsos_garmentsos_database", "garmentsos_garmentsos_storage")) {
        try {
            docker volume rm -f $volume 2>$null | Out-Null
            Write-Host "Removed Docker volume if present: $volume"
        } catch {
        }
    }
}

function Remove-GarmentsLocalCaches($TargetDir) {
    $candidates = @(
        (Join-Path $TargetDir ".env"),
        (Join-Path $TargetDir ".pending-launcher-update.json"),
        (Join-Path $TargetDir "pending-launcher-update.log"),
        (Join-Path $TargetDir "pending-launcher-update.log.old"),
        (Join-Path $TargetDir "storage\app\update-lock.json"),
        (Join-Path $TargetDir "updates\active-update.json"),
        (Join-Path $TargetDir "updates\GarmentsOS-PRO.exe.pending"),
        (Join-Path $TargetDir "storage\app\install-id.txt"),
        (Join-Path $TargetDir "storage\app\license\verify-cache.json"),
        (Join-Path $TargetDir "storage\app\license\registration-cache.json"),
        (Join-Path $TargetDir "storage\app\license\request-cache.json"),
        (Join-Path $TargetDir "storage\app\license\license.json"),
        (Join-Path $TargetDir "storage\app\license\installation.json"),
        (Join-Path $TargetDir "storage\app\license\installation.json.recovery"),
        (Join-Path $TargetDir "database\database.sqlite")
    )

    foreach ($path in $candidates) {
        try {
            if (Test-Path -LiteralPath $path) {
                Remove-Item -LiteralPath $path -Force -Recurse
                Write-ResetLog "Deleted reset state: $path"
            }
        } catch {
            Write-Warning "Could not delete reset state: $path. $($_.Exception.Message)"
        }
    }
}

function Remove-GarmentsExternalCaches() {
    $paths = @()

    foreach ($folderName in @("ApplicationData", "LocalApplicationData", "CommonApplicationData")) {
        try {
            $base = [Environment]::GetFolderPath($folderName)
            if (-not [string]::IsNullOrWhiteSpace($base)) {
                $paths += (Join-Path $base "GarmentsOS")
                $paths += (Join-Path $base "GarmentsOS PRO")
                $paths += (Join-Path $base "SparkPair\GarmentsOS")
                $paths += (Join-Path $base "SparkPair\GarmentsOS PRO")
            }
        } catch {
            Write-Warning "Could not resolve $folderName cache path. $($_.Exception.Message)"
        }
    }

    foreach ($path in ($paths | Select-Object -Unique)) {
        try {
            if (Test-Path -LiteralPath $path) {
                Remove-Item -LiteralPath $path -Recurse -Force
                Write-ResetLog "Deleted external cache: $path"
            }
        } catch {
            Write-Warning "Could not delete external cache: $path. $($_.Exception.Message)"
        }
    }
}

function Invoke-GarmentsFreshReset($TargetDir) {
    $logRoot = Join-Path ([System.IO.Path]::GetTempPath()) "GarmentsOS"
    New-Item -ItemType Directory -Force -Path $logRoot | Out-Null
    $script:ResetLogPath = Join-Path $logRoot ("fresh-reset-" + (Get-Date -Format "yyyyMMdd_HHmmss") + ".log")

    Write-ResetLog "Reset mode selected."
    Write-ResetLog "This will delete local database, license state, backups, and setup data."
    Write-ResetLog "InstallDir: $TargetDir"
    Remove-GarmentsDockerState $TargetDir
    Remove-GarmentsShortcuts
    Unregister-GarmentsProtocol
    Remove-GarmentsExternalCaches

    if (Test-Path -LiteralPath $TargetDir) {
        Repair-GarmentsInstallFolderAccess $TargetDir
        Clear-GarmentsFileAttributes $TargetDir
        Remove-GarmentsLocalCaches $TargetDir
        try {
            Remove-Item -LiteralPath $TargetDir -Recurse -Force
            Write-ResetLog "Deleted install folder: $TargetDir"
        } catch {
            throw "Fresh reset failed. Could not delete install folder: $TargetDir. $($_.Exception.Message)"
        }
    } else {
        Write-ResetLog "Install folder was already absent: $TargetDir"
    }

    Write-ResetLog "Fresh reset completed. Log: $script:ResetLogPath"
}

function Require-Command($Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name is required. Install Docker Desktop and try again."
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
    if ($script:EnvBackupCreated -or -not (Test-Path -LiteralPath $EnvPath)) {
        return
    }

    $installRoot = Split-Path -Parent $EnvPath
    $backupDir = Join-Path $installRoot "backups"
    New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupPath = Join-Path $backupDir "env_$timestamp.env"
    Copy-Item -LiteralPath $EnvPath -Destination $backupPath -Force
    $script:EnvBackupCreated = $true
    Write-Host "Environment backup created: $backupPath"
}

function Save-EnvContent($EnvPath, $Content) {
    Backup-EnvFile $EnvPath
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($EnvPath, $Content, $utf8NoBom)
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

function Invoke-GarmentsLaravelMaintenance($InstallDir) {
    try {
        Push-Location $InstallDir
        try {
            Write-Host "Running Laravel maintenance commands..."
            docker compose exec -T app sh -lc 'php artisan storage:link --force || php artisan storage:link' | Out-Host
            docker compose exec -T app php artisan optimize:clear | Out-Host
            docker compose exec -T app php artisan optimize | Out-Host
            Write-Host "Laravel maintenance completed: storage:link, optimize:clear, optimize."
        } finally {
            Pop-Location
        }
    } catch {
        Write-Warning "Laravel maintenance warning. App may still run, but cache/storage repair may be needed. $($_.Exception.Message)"
    }
}

function Read-EnvContentSafe($EnvPath) {
    try {
        return Get-Content -LiteralPath $EnvPath -Raw -ErrorAction Stop
    } catch {
        Write-Warning "Could not read .env: $($_.Exception.Message)"
        throw
    }
}

function Initialize-FreshInstallEnv($InstallDir, $EnvPath, [bool]$PreserveExistingEnv) {
    Repair-GarmentsInstallFolderAccess $InstallDir
    Clear-GarmentsFileAttributes $InstallDir

    $template = Join-Path $InstallDir ".env.example"
    if (-not (Test-Path -LiteralPath $template)) {
        throw ".env.example was not found: $template"
    }

    $envExists = Test-Path -LiteralPath $EnvPath
    if ($envExists) {
        if ($PreserveExistingEnv) {
            try {
                $null = Get-Content -LiteralPath $EnvPath -Raw -ErrorAction Stop
                Write-Host "Existing .env is readable and will be reused: $EnvPath"
                return $false
            } catch {
                Write-Warning "Existing .env cannot be read: $($_.Exception.Message)"
                $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
                $lockedPath = Join-Path (Split-Path -Parent $EnvPath) ".env.locked-$timestamp"
                try {
                    Rename-Item -LiteralPath $EnvPath -NewName (Split-Path -Leaf $lockedPath) -Force -ErrorAction Stop
                    Write-Warning "Unreadable .env renamed to: $lockedPath"
                } catch {
                    throw "Install folder is locked. Close apps or run installer as administrator."
                }
            }
        } else {
            $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
            $stalePath = Join-Path (Split-Path -Parent $EnvPath) ".env.stale-$timestamp"
            Rename-Item -LiteralPath $EnvPath -NewName (Split-Path -Leaf $stalePath) -Force -ErrorAction Stop
            Write-ResetLog "Existing .env was not reused for fresh install/reset. Renamed to: $stalePath"
        }
    }

    try {
        $templateContent = Get-Content -LiteralPath $template -Raw -ErrorAction Stop
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($EnvPath, $templateContent, $utf8NoBom)
        Write-ResetLog "Created fresh .env from template: $EnvPath"
        Write-ResetLog "Environment regenerated for fresh install."
        return $true
    } catch {
        throw "Install folder is locked. Close apps or run installer as administrator."
    }
}

function Ensure-EnvKey($EnvPath, $Key, $DefaultValue) {
    if (-not (Test-Path -LiteralPath $EnvPath)) {
        throw ".env file not found: $EnvPath"
    }

    $content = Read-EnvContentSafe $EnvPath
    $pattern = "(?m)^\s*" + [regex]::Escape($Key) + "="
    if ($content -match $pattern) {
        Write-Host "Environment key already exists: $Key"
        return
    }

    Backup-EnvFile $EnvPath
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

Require-Command docker

docker --version | Out-Host
docker compose version | Out-Host
docker info | Out-Null

$Source = Split-Path -Parent $PSScriptRoot
$ExistingInstallBeforeCopy = (Test-Path -LiteralPath (Join-Path $InstallDir "manifest.json")) -and -not $FreshReset
if ($FreshReset) {
    Invoke-GarmentsFreshReset $InstallDir
}

New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $InstallDir "backups") | Out-Null
Repair-GarmentsInstallFolderAccess $InstallDir
Clear-GarmentsFileAttributes $InstallDir

Copy-Item -Force (Join-Path $Source "docker-compose.yml") $InstallDir
Copy-Item -Force (Join-Path $Source ".env.example") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "scripts") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "docs") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "images") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "checksums") $InstallDir
Copy-Item -Force (Join-Path $Source "manifest.json") $InstallDir
if (Test-Path (Join-Path $Source "GarmentsOS-PRO.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS-PRO.exe") $InstallDir
} elseif (Test-Path (Join-Path $Source "GarmentsOS-PRO-Setup.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS-PRO-Setup.exe") (Join-Path $InstallDir "GarmentsOS-PRO.exe")
}
if (Test-Path (Join-Path $Source "GarmentsOS PRO Launcher.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS PRO Launcher.exe") $InstallDir
}
$installedSetupLauncher = Join-Path $InstallDir "GarmentsOS-PRO.exe"
$installedNestedPrimaryLauncher = Join-Path $InstallDir "launcher\GarmentsOS-PRO.exe"
$installedNestedLauncher = Join-Path $InstallDir "launcher\GarmentsOS PRO Launcher.exe"
if (-not (Test-Path $installedSetupLauncher) -and (Test-Path $installedNestedPrimaryLauncher)) {
    Copy-Item -Force $installedNestedPrimaryLauncher $installedSetupLauncher
} elseif (-not (Test-Path $installedSetupLauncher) -and (Test-Path $installedNestedLauncher)) {
    Copy-Item -Force $installedNestedLauncher $installedSetupLauncher
}
Copy-RootLaunchers $Source $InstallDir

$Manifest = Get-Content (Join-Path $InstallDir "manifest.json") | ConvertFrom-Json
if ([string]::IsNullOrWhiteSpace($Version)) {
    $Version = $Manifest.version
}

$ImageTar = Join-Path $InstallDir $Manifest.image_tar
if (-not (Test-Path $ImageTar)) {
    throw "Docker image tar not found: $ImageTar"
}

docker load -i $ImageTar | Out-Host

$EnvPath = Join-Path $InstallDir ".env"
$EnvCreated = Initialize-FreshInstallEnv $InstallDir $EnvPath $ExistingInstallBeforeCopy
if ($EnvCreated) {
    Write-ResetLog "Fresh install will generate a new install_id when the app starts."
}

Ensure-GarmentsUpdaterEnvKeys $EnvPath
Ensure-GarmentsLicenseEnvKeys $EnvPath
Ensure-GarmentsFirewallRule $InstallDir $Port

$envContent = Read-EnvContentSafe $EnvPath
if ($EnvCreated) {
    $envContent = Set-EnvLine $envContent "APP_URL" "http://localhost:$Port"
    $envContent = Set-EnvLine $envContent "APP_PORT" $Port
    $envContent = Set-EnvLine $envContent "DB_DATABASE" "/var/www/html/database/runtime/database.sqlite"
    $envContent = Set-EnvLine $envContent "LICENSE_ENABLED" "true"
    $envContent = Set-EnvLine $envContent "LICENSE_ENFORCEMENT_ENABLED" "true"
    if ($envContent -match '(?m)^APP_KEY=\s*$') {
        $bytes = New-Object byte[] 32
        $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        try {
            $rng.GetBytes($bytes)
        } finally {
            $rng.Dispose()
        }

        $appKey = "base64:" + [Convert]::ToBase64String($bytes)
        $envContent = $envContent -replace '(?m)^APP_KEY=\s*$', "APP_KEY=$appKey"
    }
    $envContent = Set-EnvLine $envContent "GARMENTSOS_IMAGE" $Manifest.image
    $envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "true"
    Save-EnvContent $EnvPath $envContent
} else {
    Ensure-EnvKey $EnvPath "APP_PORT" $Port
    Ensure-EnvKey $EnvPath "LICENSE_ENFORCEMENT_ENABLED" "true"
    Ensure-EnvKey $EnvPath "GARMENTSOS_IMAGE" $Manifest.image
}

Push-Location $InstallDir
try {
    docker volume create garmentsos-pro_garmentsos_database | Out-Null
    docker volume create garmentsos-pro_garmentsos_storage | Out-Null
    docker compose up -d
} finally {
    Pop-Location
}

Invoke-GarmentsLaravelMaintenance $InstallDir

if ($EnvCreated) {
    $envContent = Read-EnvContentSafe $EnvPath
    $envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "false"
    Save-EnvContent $EnvPath $envContent
}

Remove-InstalledEnvTemplate $InstallDir

Install-GarmentsShortcuts $InstallDir
Register-GarmentsProtocol $InstallDir
if ($HideTechnicalFiles) {
    Hide-GarmentsTechnicalFiles $InstallDir
} else {
    Write-Host "Technical files were left visible because HideTechnicalFiles is false."
}
Protect-GarmentsInstallFolder $InstallDir

$LanIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike "127.*" -and $_.PrefixOrigin -ne "WellKnown" } |
    Select-Object -First 1 -ExpandProperty IPAddress)

Write-Host ""
Write-Host "GarmentsOS PRO Docker install complete."
Write-Host "Local URL: http://localhost:$Port"
if ($LanIp) { Write-Host "LAN URL:   http://$LanIp`:$Port" }
Write-Host "Allow Windows Firewall access for Docker/Desktop when prompted."
