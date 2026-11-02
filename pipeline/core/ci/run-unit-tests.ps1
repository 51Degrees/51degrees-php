param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

./php/run-unit-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
