param (
    [Parameter(Mandatory)][string]$RepoName,
    [Parameter(Mandatory)][string]$VariableName
)
$ErrorActionPreference = "Stop"
. "$PSScriptRoot/packages.ps1"
. (Get-ReleasePlanScript)

# common-ci's publish carries one version through its jobs. Each package here
# has its own version, tagged "<package>/<version>" (see packages.ps1), so
# that one version is only a label for the run, the highest version this
# publish releases. package-update-required.ps1 decides whether anything is
# released, and publish-package.ps1 tags each package, from the same plan.
# Only the patch number moves.
$plan = @(Get-ReleasePlan $RepoName)
if ($plan.Count -gt 0) {
    $label = ($plan.Version | Sort-Object { [version]$_ })[-1]
} else {
    # Nothing to release, so package-update-required.ps1 stops the publish
    # before the label is used. The highest released version keeps the
    # shared version check quiet.
    $released = foreach ($package in $Packages | Where-Object Publish) {
        Get-ReleasedVersions $RepoName $package.Name
    }
    $label = "$(@($released | Sort-Object)[-1])"
}

Set-Variable -Scope Global -Name $VariableName -Value $label
