param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    # False, from select-options.ps1, on every entry of a pull request but
    # one, so the browser contract runs once.
    [bool]$SeleniumContract = $true
)

# common-ci puts the keys into the environment under their _51DEGREES_ names
# before this runs: _51DEGREES_RESOURCE_KEY filled from the organization
# secret _51DEGREES_RESOURCE_KEY_BESPOKE, and _51DEGREES_LICENSE_KEY_BESPOKE,
# which the Accept-CH tests ask for one property at a time with.
if (!$env:_51DEGREES_RESOURCE_KEY) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Resource Key::_51DEGREES_RESOURCE_KEY is not set, so integration tests will not run."
    return
} elseif (!(Test-Path $RepoName/tests/51Degrees.csv)) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No CSV File::CSV file wasn't found, so integration tests will not run."
    return
}

./php/run-integration-tests.ps1 -RepoName $RepoName
$status = $LASTEXITCODE

if (-not $SeleniumContract) {
    Write-Host "Another entry runs the Selenium contract tests for this pull request."
    exit $status
}

Write-Host 'Running Selenium tests...'
try {
    # Start this repo's cloud example, pointed at the live cloud.
    Push-Location "$PSScriptRoot/../examples"
    try {
        composer install --working-dir=..
        $env:PORT = 8096
        $env:_51DEGREES_CLOUD_ENDPOINT = "https://cloud.51degrees.com/api/v4/"
        $example = php -S "localhost:$env:PORT" cloud/gettingStartedWeb.php 2>&1 &
    } finally { Pop-Location }

    # Get the shared contract tests.
    if (-not (Test-Path selenium-api-tests)) {
        git clone --depth 1 https://github.com/51Degrees/selenium-api-tests.git
    }
    # Wait for the example to come up.
    curl -sS -o /dev/null --retry 5 --retry-connrefused "http://localhost:$env:PORT"

    # Every browser test needs the client-side script, so check the example
    # serves it first. Otherwise a broken route shows only as each test timing
    # out, and the example's output is never printed.
    $coreJs = Join-Path ([IO.Path]::GetTempPath()) '51Degrees.core.js'
    $answer = curl -sS -o $coreJs -w '%{http_code} %{content_type}' "http://localhost:$env:PORT/51Degrees.core.js"
    if ($answer -notlike '200 application/x-javascript*' -or
        -not (Select-String -Quiet -SimpleMatch 'fiftyoneDegreesManager' $coreJs)) {
        throw "The example answered /51Degrees.core.js with '$answer' and no client-side script."
    }

    $env:CLOUD_ROOT_URL = "https://cloud.51degrees.com/"
    # The contract tests read the key under this name.
    $env:PAID_RESOURCE_KEY = $env:_51DEGREES_RESOURCE_KEY
    $env:EXAMPLE_URL = "http://localhost:$env:PORT"
    $env:EXAMPLE_LANG = 'php'
    # Normal verbosity prints each test's time, passed or failed.
    dotnet test selenium-api-tests -c Release --filter TestCategory=Contract --logger "console;verbosity=normal"
} catch {
    if ($example) { Write-Host '>>> example app output >>>'; Receive-Job $example | Out-Host; Write-Host '<<< app output <<<' }
    throw
} finally {
    if ($example) { Remove-Job -Force $example }
}
