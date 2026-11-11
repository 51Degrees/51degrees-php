param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

# Run the PHP unit/integration tests. The on-premise tests are skipped by the
# suite itself when the native extension or a data file is unavailable.
# Mirrors device-detection-php-onpremise/ci/run-unit-tests.ps1.

$sep = [IO.Path]::PathSeparator
if (Test-Path "$PWD/$RepoName/php.ini") {
    $env:PHP_INI_SCAN_DIR = "$sep$PWD/$RepoName"
}

$liteFile = "$PWD/$RepoName/assets/51Degrees-LiteIpiV41.ipi"
if (Test-Path $liteFile) {
    $env:_51DEGREES_IPI_PATH = $liteFile
} else {
    Write-Output "::warning::No on-premise IP intelligence data file found; on-premise tests will be skipped."
}

./php/run-unit-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
