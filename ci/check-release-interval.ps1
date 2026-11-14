param (
    [Parameter(Mandatory)][string]$RepoName,
    [string]$Repository,
    [string]$HowToAllow
)
$ErrorActionPreference = "Stop"
. "$PSScriptRoot/packages.ps1"
. (Get-ReleasePlanScript)

# The shared publish runs this in place of its own check, because each package
# here has its own version, see packages.ps1. Every package about to be
# published goes through the shared steps/check-release-interval.ps1, run
# from the common-ci root, against its own "<package>/<version>" tags, so
# each package gets at most one new version a week. That reads
# ALLOW_EARLY_RELEASE and WEEKLY_RELEASES_FROM from the environment.

$refused = @()
foreach ($release in @(Get-ReleasePlan $RepoName)) {
    Write-Host "$($release.Name) $($release.Version):"
    $arguments = @{ RepoName = $RepoName; TagPrefix = $release.Name }
    if ($Repository) {
        $arguments.Repository = $Repository
    }
    if ($HowToAllow) {
        $arguments.HowToAllow = $HowToAllow
    }
    $global:LASTEXITCODE = 0
    ./steps/check-release-interval.ps1 @arguments
    if ($LASTEXITCODE -ne 0) {
        $refused += $release.Name
    }
}

if ($refused) {
    Write-Host "Nothing is released, because these packages were released less than a week ago: $($refused -join ', ')."
    exit 1
}
exit 0
