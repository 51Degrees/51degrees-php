param (
    [Parameter(Mandatory)][string]$RepoName,
    [Parameter(Mandatory)][string]$OrgName,
    [bool]$DryRun = $false
)
$ErrorActionPreference = "Stop"
$PSNativeCommandUseErrorActionPreference = $true
. "$PSScriptRoot/packages.ps1"
. (Get-ReleasePlanScript)

# Nothing is uploaded. Packagist reads the mirrors, not this repository. Each
# package this publish releases is tagged "<package>/<version>" here, and that
# tag starts .github/workflows/mirror.yml, or did-publish.yml for
# pipeline/did, which puts the version on the package's mirror for Packagist
# to release.
#
# The plan is read before anything is tagged, because the tags made below
# change what the plan would say.
$plan = @(Get-ReleasePlan $RepoName)

foreach ($release in $plan) {
    $tag = "$($release.Name)/$($release.Version)"
    git -C $RepoName tag $tag
    if ($DryRun -eq $true) {
        Write-Output "Dry run - tagged '$tag' locally, not pushed, no release made"
        continue
    }
    # One tag a push, because GitHub starts no workflow for a push of more
    # than three tags. In the order of the plan, so a run of mirror.yml that
    # sees a package's tag also sees the tags of the packages it requires.
    git -C $RepoName push origin "refs/tags/$tag"

    $notes = "$($release.Name) $($release.Version): $($release.Reason)."
    if ($null -ne $release.Previous) {
        $package = Get-Packages $RepoName | Where-Object Name -eq $release.Name
        $range = "$($release.Name)/$($release.Previous)..HEAD"
        $commits = git -C $RepoName log --format='- %s (%h)' $range `
            -- (Get-PackagePaths $package)
        if ($commits) {
            $notes += "`n`n" + ($commits -join "`n")
        }
    }
    gh -R "$OrgName/$RepoName" release create $tag --verify-tag `
        --title "$($release.Name) $($release.Version)" --notes $notes
}

# Tells common-ci's publish not to add its own single tag and release.
$global:SkipUpdateTag = $true
