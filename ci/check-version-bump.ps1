param (
    [Parameter(Mandatory)][string]$RepoName,
    [string]$Version
)
$ErrorActionPreference = "Stop"
. "$PSScriptRoot/packages.ps1"
. (Get-ReleasePlanScript)

# The shared publish runs this in place of its own check, because each package
# here has its own version, see packages.ps1. Every package about to be
# published goes through the shared steps/check-version-bump.ps1, run from the
# common-ci root, against its own "<package>/<version>" tags. That refuses a
# major or minor move unless ALLOW_VERSION_BUMP is 'true'.

Write-Host "The publish worked out $Version, the highest version it releases. Each package is checked on its own."

$refused = @()
foreach ($release in @(Get-ReleasePlan $RepoName)) {
    $global:LASTEXITCODE = 0
    ./steps/check-version-bump.ps1 -Version $release.Version `
        -RepoName $RepoName -TagPrefix $release.Name
    if ($LASTEXITCODE -ne 0) {
        $refused += $release.Name
    }
}

if ($refused) {
    Write-Host "Nothing is published, because these packages were refused: $($refused -join ', ')."
    exit 1
}
exit 0
