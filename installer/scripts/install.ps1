#Requires -Version 5.1
<#
.SYNOPSIS
    KMP Installer for Windows.

.DESCRIPTION
    Downloads and installs the kmp binary for Windows.
    The binary is saved to $env:LOCALAPPDATA\kmp\kmp.exe
    and the directory is added to the user PATH if needed.

.PARAMETER Version
    Specific version to install (e.g. v1.0.0). Defaults to latest.

.EXAMPLE
    # Install latest version
    irm https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.ps1 | iex

    # Install specific version
    .\install.ps1 -Version v1.0.0
#>

param(
    [string]$Version = "latest"
)

$ErrorActionPreference = "Stop"

$Repo = "jhandel/KMP"
$BinaryName = "kmp-windows-amd64.exe"
$InstallDir = Join-Path $env:LOCALAPPDATA "kmp"
$InstallPath = Join-Path $InstallDir "kmp.exe"

# --- Helper functions ---

function Write-Info {
    param([string]$Message)
    Write-Host "i  " -ForegroundColor Cyan -NoNewline
    Write-Host $Message
}

function Write-Ok {
    param([string]$Message)
    Write-Host "OK " -ForegroundColor Green -NoNewline
    Write-Host $Message
}

function Write-Warn {
    param([string]$Message)
    Write-Host "!  " -ForegroundColor Yellow -NoNewline
    Write-Host $Message
}

function Write-Err {
    param([string]$Message)
    Write-Host "X  " -ForegroundColor Red -NoNewline
    Write-Host $Message
}

function Get-DownloadUrl {
    $base = "https://github.com/$Repo/releases"
    if ($Version -eq "latest") {
        return "$base/latest/download/$BinaryName"
    } else {
        return "$base/download/$Version/$BinaryName"
    }
}

function Add-ToUserPath {
    param([string]$Dir)

    $currentPath = [Environment]::GetEnvironmentVariable("PATH", "User")
    if ($currentPath -and $currentPath.Split(';') -contains $Dir) {
        return $false
    }

    $newPath = if ($currentPath) { "$Dir;$currentPath" } else { $Dir }
    [Environment]::SetEnvironmentVariable("PATH", $newPath, "User")

    # Update current session PATH too
    if ($env:PATH -notlike "*$Dir*") {
        $env:PATH = "$Dir;$env:PATH"
    }

    return $true
}

function Write-ManualInstructions {
    $url = Get-DownloadUrl
    Write-Host ""
    Write-Warn "Automatic installation failed. You can install manually:"
    Write-Host ""
    Write-Host "  1. Download the binary:" -ForegroundColor White
    Write-Host "     Invoke-WebRequest -Uri '$url' -OutFile 'kmp.exe'" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  2. Move it to a directory in your PATH:" -ForegroundColor White
    Write-Host "     Move-Item kmp.exe '$InstallDir\kmp.exe'" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  3. Verify installation:" -ForegroundColor White
    Write-Host "     kmp --version" -ForegroundColor Gray
    Write-Host ""
}

# --- Main ---

function Main {
    Write-Host ""
    Write-Host "KMP Installer" -ForegroundColor White -BackgroundColor DarkBlue
    Write-Host ([char]0x2500 * 30)
    Write-Host ""

    # Detect architecture
    $arch = if ([Environment]::Is64BitOperatingSystem) { "amd64" } else { "x86" }
    if ($arch -ne "amd64") {
        Write-Err "Unsupported architecture: $arch (only amd64 is supported)"
        exit 1
    }

    Write-Info "Detected platform: windows/$arch"
    if ($Version -ne "latest") {
        Write-Info "Requested version: $Version"
    }

    $url = Get-DownloadUrl
    Write-Info "Downloading from: $url"

    # Ensure install directory exists
    if (-not (Test-Path $InstallDir)) {
        New-Item -ItemType Directory -Path $InstallDir -Force | Out-Null
        Write-Info "Created directory: $InstallDir"
    }

    # Download binary
    $tmpFile = Join-Path $env:TEMP "kmp-download-$([guid]::NewGuid().ToString('N')).exe"
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $url -OutFile $tmpFile -UseBasicParsing
    } catch {
        Write-Err "Download failed: $_"
        Write-ManualInstructions
        exit 1
    }

    # Verify download
    if (-not (Test-Path $tmpFile)) {
        Write-Err "Downloaded file not found"
        Write-ManualInstructions
        exit 1
    }

    $fileSize = (Get-Item $tmpFile).Length
    if ($fileSize -eq 0) {
        Write-Err "Downloaded file is empty"
        Remove-Item -Path $tmpFile -Force -ErrorAction SilentlyContinue
        Write-ManualInstructions
        exit 1
    }

    Write-Ok "Download complete ($([math]::Round($fileSize / 1MB, 2)) MB)"

    # Move binary to install location
    try {
        Move-Item -Path $tmpFile -Destination $InstallPath -Force
    } catch {
        Write-Err "Failed to move binary to $InstallPath : $_"
        Remove-Item -Path $tmpFile -Force -ErrorAction SilentlyContinue
        exit 1
    }

    Write-Ok "Installed to: $InstallPath"

    # Add to PATH if needed
    $pathAdded = Add-ToUserPath -Dir $InstallDir
    if ($pathAdded) {
        Write-Ok "Added $InstallDir to user PATH"
        Write-Warn "Restart your terminal for PATH changes to take effect."
    } else {
        Write-Info "$InstallDir is already in PATH"
    }

    # Success
    Write-Host ""
    Write-Host "Installation complete!" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Next steps:" -ForegroundColor White
    Write-Host "    kmp install" -ForegroundColor Cyan -NoNewline
    Write-Host "      - Set up your KMP environment"
    Write-Host "    kmp --help" -ForegroundColor Cyan -NoNewline
    Write-Host "       - See available commands"
    Write-Host ""
}

Main
