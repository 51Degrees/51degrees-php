param (
    [Parameter(Mandatory)][string]$RepoName,
    [Parameter(Mandatory)][string]$OrgName,
    [bool]$DryRun = $false
)
$ErrorActionPreference = "Stop"
$PSNativeCommandUseErrorActionPreference = $true
. "$PSScriptRoot/packages.ps1"
. (Get-ReleasePlanScript)

# Nothing is uploaded to a registry. Packagist reads the mirrors, not this
# repository. Each package this publish releases is tagged
# "<package>/<version>" here, and that tag starts .github/workflows/mirror.yml,
# or did-publish.yml for pipeline/did, which puts the version on the
# package's mirror for Packagist to release.
#
# Each package's GitHub release attaches exactly the file Packagist serves for
# that version, a zip of the commit its mirror is tagged with (see
# New-ReleaseArchive in packages.ps1), in the same call that creates the
# release, because the SourceForge project's Files sync from the GitHub
# releases and ignore files added after a release is published.
#
# The plan is read before anything is tagged, because the tags made below
# change what the plan would say.
$plan = @(Get-ReleasePlan $RepoName)

# The mirror commits are git subtree splits, which need the grafts that
# ci/prepare-split.sh makes in this checkout.
if (-not (git -C $RepoName replace --list)) {
    Push-Location $RepoName
    try {
        & bash "$($PSScriptRoot -replace '\\', '/')/prepare-split.sh"
    } finally {
        Pop-Location
    }
}
$work = Join-Path ([IO.Path]::GetTempPath()) "release-files-$PID"
New-Item -ItemType Directory -Force -Path $work | Out-Null

foreach ($release in $plan) {
    $tag = "$($release.Name)/$($release.Version)"
    $package = $Packages | Where-Object Name -eq $release.Name
    git -C $RepoName tag $tag
    # Made after the tag, so a package's published composer.json requires the
    # versions tagged with it, as ci/mirror-tags.ps1 makes it.
    $file = New-ReleaseArchive -RepoName $RepoName -Package $package `
        -Version $release.Version -Work $work
    if ($DryRun -eq $true) {
        Write-Output "Dry run - tagged '$tag' locally, not pushed, no release made. The release would attach $(Split-Path -Leaf $file)"
        continue
    }
    # One tag a push, because GitHub starts no workflow for a push of more
    # than three tags. In the order of the plan, so a run of mirror.yml that
    # sees a package's tag also sees the tags of the packages it requires.
    git -C $RepoName push origin "refs/tags/$tag"

    $notes = "$($release.Name) $($release.Version): $($release.Reason)."
    if ($null -ne $release.Previous) {
        $range = "$($release.Name)/$($release.Previous)..HEAD"
        $commits = git -C $RepoName log --format='- %s (%h)' $range `
            -- (Get-PackagePaths $package)
        if ($commits) {
            $notes += "`n`n" + ($commits -join "`n")
        }
    }
    gh -R "$OrgName/$RepoName" release create $tag $file --verify-tag `
        --title "$($release.Name) $($release.Version)" --notes $notes
}

Remove-Item -Recurse -Force $work

# Tells common-ci's publish not to add its own single tag and release.
$global:SkipUpdateTag = $true
