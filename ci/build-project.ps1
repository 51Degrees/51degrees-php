param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# One composer install at the root, which links every package in the root
# composer.json to its directory here.
Install-RootComposer -RepoName $RepoName
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Set-RootAutoload -RepoName $RepoName

Invoke-PackageScript -RepoName $RepoName -Script build-project -Vendor Own
