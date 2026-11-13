param (
    [Parameter(Mandatory)][string]$RepoName,
    [string]$Version
)
$ErrorActionPreference = "Stop"
. "$PSScriptRoot/packages.ps1"
. (Get-ReleasePlanScript)

# Called by common-ci's publish in place of its own check, because a plain tag
# lookup cannot tell the packages here apart. Exits 0 when the release plan
# has something in it and 1 when it is empty, as common-ci's check does.
# $Version is common-ci's label for the run and is not used here.

$plan = @(Get-ReleasePlan $RepoName)
if ($plan.Count -eq 0) {
    Write-Output "No package changed since its last release, so there is nothing to publish"
    exit 1
}

Write-Output "This publish releases:"
$plan | Format-Table Name, Previous, Version, Reason -AutoSize | Out-String |
    Write-Output

# The requirements each released package is published with, worked out here
# from the plan, so that a composer.json and packages.json that disagree stop
# the publish before anything is tagged. mirror.yml works them out again from
# the tags.
$versions = @{}
foreach ($package in $Packages | Where-Object Publish) {
    $release = $plan | Where-Object Name -eq $package.Name
    $released = @(Get-ReleasedVersions $RepoName $package.Name)
    if ($null -ne $release) {
        $versions[$package.Name] = $release.Version
    } elseif ($released.Count -gt 0) {
        $versions[$package.Name] = "$($released[-1])"
    }
}
foreach ($release in $plan) {
    $package = $Packages | Where-Object Name -eq $release.Name
    $require = (Get-PublishedComposerJson -RepoName $RepoName -Package $package `
        -Versions $versions | ConvertFrom-Json).require
    $own = @($require.PSObject.Properties | Where-Object Name -like '51degrees/*' |
        ForEach-Object { "$($_.Name) $($_.Value)" })
    $text = $own.Count -gt 0 ? ($own -join ', ') : 'no other 51Degrees package'
    Write-Output "$($release.Name) $($release.Version) requires $text"
}

# A version is published once its mirror has it, and is never replaced.
# mirror-tags.ps1 refuses a version that is not above the mirror's highest,
# but only after publish-package.ps1 has tagged this repository and made the
# GitHub releases, so the same check is made here first. It fails when a
# package's last release has no "<package>/<version>" tag here, because the
# plan then gives a version the mirror already has.
$published = @()
foreach ($release in $plan) {
    $package = $Packages | Where-Object Name -eq $release.Name
    if (-not $package.Mirror) {
        continue
    }
    $highest = Get-MirrorHighestVersion $package.Mirror
    if ($null -ne $highest -and $highest -ge [version]$release.Version) {
        $published += "$($release.Name) $($release.Version), as $($package.Mirror) already has $highest"
    }
}
if ($published) {
    throw "Nothing is published, because the plan gives versions already published: " +
        "$($published -join '; '). Tag each package here '<package>/<version>' at " +
        "the commit its mirror's highest version was released from, then run this again."
}

exit 0
