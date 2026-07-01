param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    # Passed by the nightly-pipeline / pull-requests workflows as secrets.
    [string]$IpIntelligenceUrl,
    [string]$DeviceDetection,
    [string]$DeviceDetectionUrl
)
$ErrorActionPreference = 'Stop'

# Download the IP intelligence data file used by the on-premise tests and
# examples. Mirrors device-detection-php-onpremise/ci/fetch-assets.ps1.
#
# When an IpIntelligenceUrl (the enterprise data file distributor URL) is
# provided by CI it is used via the shared common-ci fetch step; otherwise the
# freely available Lite file is downloaded so the on-premise tests and examples
# can still run.

if ($env:GITHUB_JOB -eq "PreBuild") {
    Write-Host "Skipping assets fetching"
    exit 0
}

$assetsDir = [IO.Path]::Combine($RepoName, "assets")
New-Item -ItemType Directory -Force -Path $assetsDir | Out-Null

# Prefer the enterprise data file from the distributor when CI supplies its
# URL (delegating to the shared common-ci fetch step, as device-detection
# does). Fall back to the public Lite file otherwise.
if ($IpIntelligenceUrl -and (Test-Path "./steps/fetch-assets.ps1")) {
    ./steps/fetch-assets.ps1 -IpIntelligenceUrl:$IpIntelligenceUrl -Assets @("51Degrees-EnterpriseIpiV41.ipi")
    if (Test-Path ([IO.Path]::Combine($assetsDir, "51Degrees-EnterpriseIpiV41.ipi"))) {
        Write-Host "IP intelligence enterprise data file fetched."
        exit 0
    }
}

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
