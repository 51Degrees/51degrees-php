param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$Version
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# TEMPORARY, on a tmp branch only: lets common-ci's Package job run
# ci/publish-package.ps1, as a dry run, off main.
$global:PublishAnyBranch = $true

# Packagist serves the source, so there is no built package to install. Every
# package's install-package.ps1 builds the project in place, and so does this.
Install-RootComposer -RepoName $RepoName
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Set-RootAutoload -RepoName $RepoName

Invoke-PackageScript -RepoName $RepoName -Script install-package -Parameters @{ Version = $Version } -Vendor Own
