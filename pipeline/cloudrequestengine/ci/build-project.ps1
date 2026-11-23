param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

# This script only runs in nightly-pr-to-main workdflow, where we should use
# development versions of dependencies
$env:COMPOSER = "composer.json"

./php/build-project.ps1 -RepoName $RepoName

exit $LASTEXITCODE
