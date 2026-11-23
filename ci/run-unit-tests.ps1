param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

Invoke-PackageScript -RepoName $RepoName -Script run-unit-tests
