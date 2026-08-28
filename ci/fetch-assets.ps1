param (
    # Only parameters that common-ci can actually supply may be declared here,
    # and they must be named after the workflow secrets.
    #
    # This script has two callers. On the build-and-test path it is invoked
    # through steps/run-script.ps1, which passes every matching option. But the
    # Configure job reaches it first, through the prefetch_assets composite
    # action, and that builds its parameter set from the workflow secrets and
    # nothing else:
    #
    #   $accepted = (Get-Command "$env:repo/ci/fetch-assets.ps1").Parameters
    #   ($env:secrets | ConvertFrom-Json -AsHashtable).GetEnumerator()
    #       .Where({$accepted.ContainsKey($_.Key)})...
    #
    # So a mandatory parameter it cannot supply - RepoName used to be one - makes
    # PowerShell prompt for a value, hit EOF on the runner and fail Configure,
    # which skips Build and Test for the whole matrix. Use $PSScriptRoot to reach
    # the repository instead of taking its name as a parameter, the same as
    # device-detection-php, device-detection-php-onpremise, ip-intelligence-java
    # and ip-intelligence-dotnet.
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

# The Lite file is public, and is the one ci/run-unit-tests.ps1 points
# IPINTELLIGENCEDATAFILE at, so it is always fetched. The Enterprise file needs
# the distributor URL, so it is only requested when CI supplies that secret.
$assets = @('51Degrees-LiteIpiV41.ipi')
if ($IpIntelligenceUrl) {
    $assets += '51Degrees-EnterpriseIpiV41.ipi'
}

# The shared step writes into ./assets relative to the working directory, which
# is also the path prefetch_assets caches, so both callers agree on one location.
./steps/fetch-assets.ps1 -IpIntelligenceUrl:$IpIntelligenceUrl -Assets $assets

# The tests and examples resolve the data file relative to the repository
# (IpIntelligenceOnPremiseTests::locateDataFile looks in ./assets first), so link
# the fetched copies into the working tree rather than copying them - the
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
