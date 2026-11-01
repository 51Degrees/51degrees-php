param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$OrgName,
    [bool]$DryRun = $false
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# The packages that have their own update-packages.ps1 are
# pipeline-php-core, which refreshes javascript-templates, and
# ip-intelligence-php, which has nothing to update.
Invoke-PackageScript -RepoName $RepoName -Script update-packages -Parameters @{ OrgName = $OrgName; DryRun = $DryRun }
