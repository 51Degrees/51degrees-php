param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

./php/build-project.ps1 -RepoName $RepoName

exit $LASTEXITCODE
