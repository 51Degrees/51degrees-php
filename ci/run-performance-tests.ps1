param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$Name,
    [boolean]$RunPerformance = $true
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# device-detection-php-onpremise reads its user agents from ../assets, relative
# to its own folder. That is the shared assets folder when the package is the
# repository, and the folder beside the packages here, so link it there.
$assets = Join-Path $RepoName 'assets'
if ((Test-Path assets) -and -not (Test-Path $assets)) {
    New-Item -ItemType SymbolicLink -Path $assets -Target (Resolve-Path assets).Path | Out-Null
}

# Only device-detection-php-onpremise measures anything. It writes
# results_<Name>.json, which Invoke-PackageScript moves to
# <repository>/test-results/performance-summary, where common-ci uploads it.
Invoke-PackageScript -RepoName $RepoName -Script run-performance-tests -Parameters @{ Name = $Name; RunPerformance = $RunPerformance }
