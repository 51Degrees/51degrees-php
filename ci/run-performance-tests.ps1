param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$Name,
    [boolean]$RunPerformance = $true
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# device-detection/onpremise reads its user agents from ../assets, relative
# to its own folder. That is the shared assets folder when the package is the
# repository. Here it is the folder beside the package, device-detection, so
# the shared folder is linked there, and beside the root for the packages
# that sit directly under it.
if (Test-Path assets) {
    $target = (Resolve-Path assets).Path
    $parents = @('') + @($Packages | ForEach-Object { Split-Path $_.Path -Parent } | Where-Object { $_ })
    foreach ($parent in $parents | Sort-Object -Unique) {
        $assets = Join-Path $RepoName $parent 'assets'
        if (-not (Test-Path $assets)) {
            New-Item -ItemType SymbolicLink -Path $assets -Target $target | Out-Null
        }
    }
}

# device-detection/onpremise measures detection and writes
# results_<Name>.json, which Invoke-PackageScript moves to
# <repository>/test-results/performance-summary, where common-ci uploads it.
# pipeline/core times minification in the JavaScript Builder and writes no
# summary.
Invoke-PackageScript -RepoName $RepoName -Script run-performance-tests -Parameters @{ Name = $Name; RunPerformance = $RunPerformance }
