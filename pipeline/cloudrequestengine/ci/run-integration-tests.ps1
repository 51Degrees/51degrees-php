param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

# common-ci puts each organization secret and variable into the environment
# under its own name before this runs. The tests read
# _51DEGREES_RESOURCE_KEY_BESPOKE, the organization secret holding the key
# that carries every property they ask for.
if (!$env:_51DEGREES_RESOURCE_KEY_BESPOKE) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No Resource Key::_51DEGREES_RESOURCE_KEY_BESPOKE is not set, so integration tests will not run."
    return
}

./php/run-integration-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
