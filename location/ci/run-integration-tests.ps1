param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

# common-ci puts the resource key into the environment as
# _51DEGREES_RESOURCE_KEY before this runs, filled from the organization
# secret _51DEGREES_RESOURCE_KEY_BESPOKE.
if (!$env:_51DEGREES_RESOURCE_KEY) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Resource Key::_51DEGREES_RESOURCE_KEY is not set, so integration tests will not run."
    return
}

./php/run-integration-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
