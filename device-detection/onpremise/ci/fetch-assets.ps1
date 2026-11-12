param (
    [string]$DeviceDetection,
    [string]$DeviceDetectionUrl
)
$ErrorActionPreference = 'Stop'

# PreBuild is a job from the nightly-publish-main workflow
if ($env:GITHUB_JOB -eq "PreBuild") {
    Write-Host "Skipping assets fetching"
    exit 0
}

$assets = "TAC-HashV41.hash", "20000 User Agents.csv", "51Degrees-LiteV4.1.hash"
./steps/fetch-assets.ps1 -DeviceDetection:$DeviceDetection -DeviceDetectionUrl:$DeviceDetectionUrl -Assets $assets

# The tests that run the examples with the free Lite data file look for it in
# the data repository, where a fresh clone downloads it, so the asset is
# linked there rather than downloaded again.
New-Item -ItemType SymbolicLink -Force -Target "$PWD/assets/51Degrees-LiteV4.1.hash" -Path "$PSScriptRoot/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash" | Out-Null
