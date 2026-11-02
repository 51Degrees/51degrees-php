param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Hashtable]$Keys
)

# Run the cloud integration tests, which need a resource key. The cloud tests
# fall back to a mocked HTTP client when no key is supplied, so this simply
# surfaces the key (if present) via the resource_key environment variable.
# Mirrors device-detection-php/ci/run-integration-tests.ps1.

if ($Keys -and $Keys.TestResourceKey) {
    $env:resource_key = $Keys.TestResourceKey
}

./php/run-integration-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
