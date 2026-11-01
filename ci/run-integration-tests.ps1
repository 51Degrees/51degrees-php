param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [hashtable]$Keys = @{}
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

Invoke-PackageScript -RepoName $RepoName -Script run-integration-tests -Parameters @{ Keys = $Keys }
