param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [hashtable]$Keys = @{}
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# Chrome's first start on a fresh runner is many times slower than the starts
# after it, so it is made here, before the packages' Selenium contract tests,
# rather than inside their first test. The tests still run when it cannot be
# made.
try {
    & "$PSScriptRoot/start-chrome-once.ps1"
} catch {
    Write-Host "::warning title=Chrome not started::$_"
}

# The device-detection/cloud script runs composer install in its package for
# the Selenium contract tests. Without composer.ci.json that install takes the
# pipeline packages from Packagist, so the example would run with the pipeline
# code of the last release rather than the code it is released with.
Write-CheckoutComposer -RepoName $RepoName -Package 'device-detection/cloud' `
    -Pipeline 'core', 'engines', 'cloudrequestengine'

Invoke-PackageScript -RepoName $RepoName -Script run-integration-tests -Parameters @{ Keys = $Keys } `
    -Environment @{ 'device-detection/cloud' = @{ COMPOSER = 'composer.ci.json' } }
