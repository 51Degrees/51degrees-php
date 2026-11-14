param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [boolean]$RunPerformance = $true
)

if (!$RunPerformance) {
    Write-Output "Skipping performance tests"
    return
}

# The Performance suite times the JavaScript Builder with minification on and
# off, and fails when minification adds more than its limit to a request.
Push-Location $RepoName
try {
    Write-Output "Running performance tests"
    phpunit --fail-on-warning --testsuite Performance
    $code = $LASTEXITCODE
} finally {
    Pop-Location
}

exit $code
