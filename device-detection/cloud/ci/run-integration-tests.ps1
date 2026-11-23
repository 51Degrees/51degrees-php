param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    # False, from select-options.ps1, on every entry of a pull request but
    # one, so the browser contract runs once.
    [bool]$SeleniumContract = $true
)

# common-ci puts each organization secret and variable into the environment
# under its own name before this runs. The tests read
# _51DEGREES_RESOURCE_KEY_BESPOKE, the organization variable holding the key
# that carries every property they ask for,
# _51DEGREES_LICENSE_KEY_BESPOKE, the organization secret the Accept-CH tests
# ask for one property at a time with, and _51DEGREES_RESOURCE_KEY_BASIC, the
# organization variable holding a Basic key, which the Human Confidence test
# of a key without HumanConfidenceSource skips without.
if (!$env:_51DEGREES_RESOURCE_KEY_BESPOKE) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Resource Key::_51DEGREES_RESOURCE_KEY_BESPOKE is not set, so integration tests will not run."
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
# The scripts of the other packages run in this process afterwards, so the
# example's key is put back as it was once the browser tests end.
$previousKey = $env:_51DEGREES_RESOURCE_KEY
try {
    # Start this repo's cloud example, against the cloud service that
    # _51DEGREES_CLOUD_ENDPOINT names, or the public one when it is unset.
    # The example reads the key a person sets, so it is given the key of the
    # tests under that name.
    $port = 8096
    Push-Location "$PSScriptRoot/../examples"
    try {
        composer install --working-dir=..
        $env:_51DEGREES_RESOURCE_KEY = $env:_51DEGREES_RESOURCE_KEY_BESPOKE
        $example = php -S "localhost:$port" cloud/gettingStartedWeb.php 2>&1 &
    } finally { Pop-Location }

    # Get the shared contract tests.
    if (-not (Test-Path selenium-api-tests)) {
        git clone --depth 1 https://github.com/51Degrees/selenium-api-tests.git
    }
    # Wait for the example to come up.
    curl -sS -o /dev/null --retry 5 --retry-connrefused "http://localhost:$port"

    # Every browser test needs the client-side script, so check the example
    # serves it first. Otherwise a broken route shows only as each test timing
    # out, and the example's output is never printed.
    $coreJs = Join-Path ([IO.Path]::GetTempPath()) '51Degrees.core.js'
    $answer = curl -sS -o $coreJs -w '%{http_code} %{content_type}' "http://localhost:$port/51Degrees.core.js"
    if ($answer -notlike '200 application/x-javascript*' -or
        -not (Select-String -Quiet -SimpleMatch 'fiftyoneDegreesManager' $coreJs)) {
        throw "The example answered /51Degrees.core.js with '$answer' and no client-side script."
    }

    # The contract tests open the example that is already running, which was
    # given its key and its cloud service above, so they need neither.
    $env:EXAMPLE_URL = "http://localhost:$port"
    $env:EXAMPLE_LANG = 'php'
    # Normal verbosity prints each test's time, passed or failed.
    dotnet test selenium-api-tests -c Release --filter TestCategory=Contract --logger "console;verbosity=normal"
} catch {
    if ($example) { Write-Host '>>> example app output >>>'; Receive-Job $example | Out-Host; Write-Host '<<< app output <<<' }
    throw
} finally {
    if ($example) { Remove-Job -Force $example }
    $env:_51DEGREES_RESOURCE_KEY = $previousKey
}
