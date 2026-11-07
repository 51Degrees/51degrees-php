param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [hashtable]$Keys = @{},
    # False, from select-options.ps1, on every entry of a pull request but
    # one, so the browser contract runs once.
    [bool]$SeleniumContract = $true
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

Invoke-PackageScript -RepoName $RepoName -Script run-integration-tests -Parameters @{
    Keys = $Keys
    SeleniumContract = $SeleniumContract
}
