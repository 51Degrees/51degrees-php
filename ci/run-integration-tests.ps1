param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    # False, from select-options.ps1, on every entry of a pull request but
    # one, so the browser contract runs once.
    [bool]$SeleniumContract = $true
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# A browser's first start on a fresh runner is many times slower than the
# starts after it, so Chrome and Firefox are started here, before the packages'
# Selenium contract tests, rather than inside their first tests. The tests still
# run when they cannot be started. An entry that skips the contract starts none.
if ($SeleniumContract) {
    try {
        & "$PSScriptRoot/start-browsers-once.ps1"
    } catch {
        Write-Host "::warning title=Browsers not started::$_"
    }
}

# The device-detection/cloud script runs composer install in its package for
# the Selenium contract tests. Without composer.ci.json that install takes the
# pipeline packages from Packagist, so the example would run with the pipeline
# code of the last release rather than the code it is released with.
Write-CheckoutComposer -RepoName $RepoName -Package 'device-detection/cloud' `
    -Pipeline 'core', 'engines', 'cloudrequestengine'

# The packages read their keys from the environment, where common-ci puts
# them under their _51DEGREES_ names before this runs.
Invoke-PackageScript -RepoName $RepoName -Script run-integration-tests -Parameters @{
    SeleniumContract = $SeleniumContract
} -Environment @{ 'device-detection/cloud' = @{ COMPOSER = 'composer.ci.json' } }

# Every package's unit and integration tests have run by now, so this finds a
# free data file that any of them downloaded rather than took from the cache.
& "$PSScriptRoot/check-no-data-file-downloads.ps1" -RepoName $RepoName
