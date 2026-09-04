param (
    # The prefetch_assets action calls this with the workflow secrets and nothing
    # else, so every parameter must be optional and named after a secret. A
    # mandatory one makes PowerShell prompt and fails the Configure job.
    [string]$IpIntelligenceUrl,
    [string]$DeviceDetection,
    [string]$DeviceDetectionUrl
)
$ErrorActionPreference = 'Stop'

# PreBuild is a job from the nightly-publish-main workflow
if ($env:GITHUB_JOB -eq "PreBuild") {
    Write-Host "Skipping assets fetching"
    exit 0
}

$assets = @('51Degrees-LiteIpiV41.ipi')
if ($IpIntelligenceUrl) {
    $assets += '51Degrees-EnterpriseIpiV41.ipi'
}

./steps/fetch-assets.ps1 -IpIntelligenceUrl:$IpIntelligenceUrl -Assets $assets

# The tests look for the data file under the repository, but the shared step
# fetches into ./assets at the workspace root. Link rather than copy - the
# Enterprise file is several GB.
$repoAssets = New-Item -ItemType Directory -Force -Path "$PSScriptRoot/../assets"
foreach ($asset in $assets) {
    $fetched = Join-Path $PWD "assets/$asset"
    if (Test-Path $fetched) {
        New-Item -ItemType SymbolicLink -Force -Target $fetched -Path (Join-Path $repoAssets $asset) | Out-Null
        Write-Host "Linked '$asset' into $repoAssets"
    } else {
        Write-Warning "Asset '$asset' was not fetched into $fetched"
    }
}
