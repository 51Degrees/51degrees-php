param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [hashtable]$Keys = @{}
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# The device-detection/cloud script runs composer install in its package for
# the Selenium contract tests. Without composer.ci.json that install takes the
# pipeline packages from Packagist, so the example would run with the pipeline
# code of the last release rather than the code it is released with.
Write-CheckoutComposer -RepoName $RepoName -Package 'device-detection/cloud' `
    -Pipeline 'core', 'engines', 'cloudrequestengine'

Invoke-PackageScript -RepoName $RepoName -Script run-integration-tests -Parameters @{ Keys = $Keys } `
    -Environment @{ 'device-detection/cloud' = @{ COMPOSER = 'composer.ci.json' } }
