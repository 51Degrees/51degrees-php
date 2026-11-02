param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [hashtable]$Keys
)

if (!$Keys.TestResourceKey) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Resource Key::No resource key was provided, so integration tests will not run."
    return
}

$env:resource_key = $Keys.TestResourceKey

./php/run-integration-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
