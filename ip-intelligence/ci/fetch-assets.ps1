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

# Every free file the examples look for, by its asset name, with the name the
# data repository gives it (LITE_DATA_FILE_NAME and ASN_DATA_FILE_NAME in
# examples/classes/ExampleConstants.php).
$freeFiles = [ordered]@{
    '51Degrees-LiteIpiV41.ipi' = '51Degrees-LiteV41.ipi'
    '51Degrees-IPIV4AsnIpiV41.ipi' = '51Degrees-IPIV4AsnIpiV41.ipi'
}

$fetch = @($assets) + @($freeFiles.Keys | Where-Object { $_ -notin $assets })
./steps/fetch-assets.ps1 -IpIntelligenceUrl:$IpIntelligenceUrl -Assets $fetch

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

# With nothing set, the examples look for the free files in the data
# repository, where a fresh clone downloads them, so the cached copies are
# linked there under the data repository's names rather than downloaded from
# the distributor in every job. tests/DownloadDataFileTests.php still downloads
# one from nothing. A package checked out on its own has no data repository
# beside it, so nothing is linked there.
$data = "$PSScriptRoot/../../cxx/data/ip-intelligence"
if (Test-Path $data) {
    foreach ($asset in $freeFiles.Keys) {
        $fetched = Join-Path $PWD "assets/$asset"
        if (Test-Path $fetched) {
            New-Item -ItemType SymbolicLink -Force -Target $fetched -Path (Join-Path $data $freeFiles[$asset]) | Out-Null
            Write-Host "Linked '$asset' into $data as '$($freeFiles[$asset])'"
        } else {
            Write-Warning "Asset '$asset' was not fetched into $fetched"
        }
    }
} else {
    Write-Host "There is no data repository at $data, so the free files are not linked there"
}
