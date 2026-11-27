$ErrorActionPreference = 'Stop'

# PreBuild is a job from the nightly-publish-main workflow
if ($env:GITHUB_JOB -eq "PreBuild") {
    Write-Host "Skipping assets fetching"
    exit 0
}

# The TAC data file comes from the Distributor with the data license key,
# which common-ci puts into the environment as _51DEGREES_LICENSE_KEY before
# this runs. Without it, as in a build with no secrets, only the free assets
# are fetched, and ci/run-unit-tests.ps1 runs the tests that need no TAC file.
$assets = @("20000 Evidence Records.yml", "51Degrees-LiteV4.1.hash")
if ($env:_51DEGREES_LICENSE_KEY) {
    $assets = @("TAC-HashV41.hash") + $assets
} else {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Data License::_51DEGREES_LICENSE_KEY is not set, so the TAC data file was not fetched."
}
./steps/fetch-assets.ps1 -Assets $assets

# The examples and tests read their data file from _51DEGREES_DD_PATH, so it
# names a file fetched here: the TAC data file, which php.ini names, when it
# was fetched, so that no example reloads the engine from another file, and
# the Lite data file otherwise. The build and test scripts run after this one
# in the same PowerShell process, so they inherit it.
$dataFile = @("$PWD/assets/TAC-HashV41.hash", "$PWD/assets/51Degrees-LiteV4.1.hash") |
    Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if ($dataFile) {
    $env:_51DEGREES_DD_PATH = $dataFile
    Write-Host "_51DEGREES_DD_PATH names $dataFile"
} else {
    Write-Warning "No device detection data file was fetched into $PWD/assets, so _51DEGREES_DD_PATH is not set"
}

# The tests that run the examples with the free Lite data file look for it in
# the data repository, where a fresh clone downloads it, so the asset is
# linked there rather than downloaded again.
New-Item -ItemType SymbolicLink -Force -Target "$PWD/assets/51Degrees-LiteV4.1.hash" -Path "$PSScriptRoot/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash" | Out-Null
