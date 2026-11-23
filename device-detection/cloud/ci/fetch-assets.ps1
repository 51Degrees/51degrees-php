if ($env:GITHUB_JOB -eq "PreBuild") {
    Write-Host "Skipping assets fetching"
    exit 0
}

# The CSV file comes from the Distributor with the data license key, which
# common-ci puts into the environment as _51DEGREES_LICENSE_KEY before this
# runs. Without it, as in a build with no secrets, nothing is fetched, and
# ci/run-integration-tests.ps1 skips the tests that need the file.
if (!$env:_51DEGREES_LICENSE_KEY) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Data License::_51DEGREES_LICENSE_KEY is not set, so the CSV file was not fetched."
    exit 0
}

./steps/fetch-assets.ps1 -Assets "51Degrees.csv"
New-Item -ItemType SymbolicLink -Force -Target "$PWD/assets/51Degrees.csv" -Path "$PSScriptRoot/../tests/51Degrees.csv"
