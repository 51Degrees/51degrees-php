param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [string]$DeviceDetection,
    [string]$DeviceDetectionUrl
)
$ErrorActionPreference = 'Stop'

# Download the IP intelligence data file used by the on-premise tests and
# examples. Mirrors device-detection-php-onpremise/ci/fetch-assets.ps1.

if ($env:GITHUB_JOB -eq "PreBuild") {
    Write-Host "Skipping assets fetching"
    exit 0
}

$assetsDir = [IO.Path]::Combine($RepoName, "assets")
New-Item -ItemType Directory -Force -Path $assetsDir | Out-Null

$liteFile = [IO.Path]::Combine($assetsDir, "51Degrees-LiteIpiV41.ipi")
if (!(Test-Path $liteFile)) {
    $url = "https://51ddatafiles.blob.core.windows.net/enterpriseipi/51Degrees-LiteIpiV41.ipi.gz"
    $gz = "$liteFile.gz"
    Write-Host "Downloading Lite IP intelligence data file..."
    Invoke-WebRequest -Uri $url -OutFile $gz
    # Decompress
    $input = [IO.File]::OpenRead($gz)
    $output = [IO.File]::Create($liteFile)
    $gzip = New-Object System.IO.Compression.GzipStream($input, [IO.Compression.CompressionMode]::Decompress)
    $gzip.CopyTo($output)
    $gzip.Dispose(); $output.Dispose(); $input.Dispose()
    Remove-Item $gz
}

Write-Host "IP intelligence data file is at $liteFile"
