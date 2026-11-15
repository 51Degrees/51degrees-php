param (
    [string]$DeviceDetection,
    [string]$CsvUrl
)

if ($env:GITHUB_JOB -eq "PreBuild") {
    Write-Host "Skipping assets fetching"
    exit 0
}

# The CSV file needs a device detection licence or the address of a copy.
# Without either, as in a build with no secrets, nothing is fetched, and
# ci/run-integration-tests.ps1 skips the tests that need the file.
if (!$DeviceDetection -and !$CsvUrl) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Device Detection Licence::No device detection licence was given, so the CSV file was not fetched."
    exit 0
}

./steps/fetch-assets.ps1 -DeviceDetection:$DeviceDetection -CsvUrl:$CsvUrl -Assets "51Degrees.csv"
New-Item -ItemType SymbolicLink -Force -Target "$PWD/assets/51Degrees.csv" -Path "$PSScriptRoot/../tests/51Degrees.csv"
