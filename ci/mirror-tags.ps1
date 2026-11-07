<#
Puts the version of each package released from the checked out commit on the
package's mirror, which is what Packagist turns into a release.

The publish tags that commit "<package>/<version>" for each package it
releases. For each such tag, this splits the package's folder out with git
subtree split, as mirror.yml does for the mirror's main, and tags the mirror
"<version>". The published composer.json requires each other package here at
the newest version tagged on or before this commit, so where a package
requires others the mirror's tag goes on a commit on top of the split that
changes only those requirements. That commit is made from fixed names and
dates, so the same tag always gives the same commit and a second run finds
the mirror already tagged.

pipeline/did is left to did-publish.yml, which assembles its release.

Every mirror is checked before any is tagged. The tags are pushed in the
order of packages.json, which lists each package after those it requires, and
the pushes stop at the first failure, so a version is never published before
one it requires. Without -Push nothing is pushed and the script says what it
would push. Run ci/prepare-split.sh first.
#>
param (
    [string]$RepoName = '.',
    [string]$Owner = '51Degrees',
    # The tag that started the run, which must be one this mirrors.
    [string]$Tag,
    [switch]$Push
)
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $true
. "$PSScriptRoot/packages.ps1"

$VersionPattern = '^\d+\.\d+\.\d+$'

# The highest "<Name>/<version>" tag here, or with -Merged the highest on or
# before HEAD, or $null.
function Get-HighestVersion([string]$Name, [switch]$Merged) {
    $arguments = @('-C', $RepoName, 'for-each-ref', '--format=%(refname:strip=2)')
    if ($Merged -eq $true) {
        $arguments += '--merged=HEAD'
    }
    $arguments += "refs/tags/$Name/"
    $versions = foreach ($ref in (& git @arguments)) {
        $text = $ref.Substring($Name.Length + 1)
        if ($text -match $VersionPattern) {
            [version]$text
        }
    }
    return $versions | Sort-Object | Select-Object -Last 1
}

function Get-MirrorUrl([string]$Mirror) {
    if ($env:MIRROR_PUSH_TOKEN) {
        return "https://x-access-token:$($env:MIRROR_PUSH_TOKEN)@github.com/$Owner/$Mirror.git"
    }
    return "https://github.com/$Owner/$Mirror.git"
}

if ($Push -eq $true -and -not $env:MIRROR_PUSH_TOKEN) {
    throw 'MIRROR_PUSH_TOKEN is not set'
}
# Without the grafts a split can take this repository's whole history, and
# pushing it would copy the repository into the mirror.
if (-not (git -C $RepoName replace --list)) {
    throw 'Run ci/prepare-split.sh before this'
}

$head = git -C $RepoName rev-parse HEAD
$atHead = @(git -C $RepoName tag --points-at HEAD)
$work = Join-Path ([IO.Path]::GetTempPath()) "mirror-tags-$PID"
New-Item -ItemType Directory -Force -Path $work | Out-Null

$failed = $false
$pushes = @()
$mirrored = @()
$earlier = @()
foreach ($package in $Packages) {
    foreach ($name in $package.DependsOn) {
        if ($earlier -notcontains $name) {
            throw "packages.json lists $($package.Name) before $name, which it requires"
        }
    }
    $earlier += $package.Name

    $prefix = "$($package.Name)/"
    $tags = @($atHead | Where-Object {
        $_.StartsWith($prefix) -and $_.Substring($prefix.Length) -match $VersionPattern
    })
    if ($tags.Count -eq 0) {
        continue
    }
    if ($tags.Count -gt 1) {
        Write-Output "::error::$head has more than one tag of $($package.Name): $($tags -join ', ')"
        $failed = $true
        continue
    }
    $version = $tags[0].Substring($prefix.Length)
    if ($package.Publish -ne $true -or -not $package.Mirror) {
        Write-Output "::error::$($tags[0]) is tagged, but packages.json does not publish $($package.Name) to a mirror"
        $failed = $true
        continue
    }
    $mirrored += $tags[0]
    if ($package.Assembled -eq $true) {
        Write-Output "$($tags[0]) is published by did-publish.yml"
        continue
    }

    # A tag pushed by hand is a path into Packagist that the publish's checks
    # do not see, and a published version cannot be taken back.
    $highest = Get-HighestVersion $package.Name
    if ($highest -ne [version]$version) {
        Write-Output "::error::$($tags[0]) is not the highest version of $($package.Name) here, which is $highest"
        $failed = $true
        continue
    }

    $versions = @{}
    $missing = @()
    foreach ($name in $package.DependsOn) {
        $required = Get-HighestVersion $name -Merged
        if ($null -eq $required) {
            $missing += $name
        } else {
            $versions[$name] = "$required"
        }
    }
    if ($missing) {
        Write-Output "::error::$($package.Name) requires $($missing -join ', '), with no version tag on or before $head"
        $failed = $true
        continue
    }
    $published = Get-PublishedComposerJson -RepoName $RepoName -Package $package `
        -Versions $versions
    $original = Get-Content -Raw (Join-Path $RepoName $package.Path 'composer.json')

    $errors = Join-Path $work 'split.err'
    try {
        $split = git -C $RepoName subtree split --prefix="$($package.Path)" 2>$errors
    } catch {
        Get-Content $errors | Write-Output
        throw
    }
    if ($published -ceq $original) {
        $commit = $split
    } else {
        $file = Join-Path $work "$($package.Name).json"
        [IO.File]::WriteAllText($file, $published)
        $blob = git -C $RepoName hash-object -w --no-filters -- $file
        $mode = (git -C $RepoName ls-tree $split -- composer.json) -split '\s+' |
            Select-Object -First 1
        $env:GIT_INDEX_FILE = Join-Path $work "$($package.Name).index"
        try {
            git -C $RepoName read-tree $split
            git -C $RepoName update-index --cacheinfo "$mode,$blob,composer.json"
            $tree = git -C $RepoName write-tree
        } finally {
            Remove-Item env:GIT_INDEX_FILE
        }
        # Fixed names and the date of the tagged commit, so that the same tag
        # always gives the same commit.
        $date = "$(git -C $RepoName log -1 --format=%ct HEAD) +0000"
        $identity = @{
            GIT_AUTHOR_NAME = 'Automation51D'; GIT_AUTHOR_EMAIL = '51DCI@51Degrees.com'
            GIT_AUTHOR_DATE = $date; GIT_COMMITTER_NAME = 'Automation51D'
            GIT_COMMITTER_EMAIL = '51DCI@51Degrees.com'; GIT_COMMITTER_DATE = $date
        }
        foreach ($key in $identity.Keys) {
            Set-Item "env:$key" $identity[$key]
        }
        try {
            $commit = git -C $RepoName commit-tree $tree -p $split `
                -m "Release $($package.Name) $version" `
                -m "$($package.Path) at $head, requiring the other 51Degrees packages at the versions released with it."
        } finally {
            foreach ($key in $identity.Keys) {
                Remove-Item "env:$key"
            }
        }
        git -C $RepoName --no-pager diff $split $commit | Write-Output
    }
    $require = @(($published | ConvertFrom-Json).require.PSObject.Properties |
        Where-Object Name -like '51degrees/*' | ForEach-Object { "$($_.Name) $($_.Value)" })
    $requires = $require.Count -gt 0 ? ($require -join ', ') : 'no other 51Degrees package'
    Write-Output "$($package.Name) $version from $($package.Path): split $split, published $commit, requires $requires"

    # A published version is never replaced. The same commit passes, so that
    # a re-run of a finished job is green. An annotated tag is listed twice,
    # and the ^{} line names the commit.
    $refs = @{}
    foreach ($line in (git ls-remote --tags (Get-MirrorUrl $package.Mirror))) {
        $sha, $ref = $line -split "`t"
        $refs[$ref] = $sha
    }
    $existing = $refs["refs/tags/$version^{}"] ?? $refs["refs/tags/$version"]
    if ($existing) {
        if ($existing -eq $commit) {
            Write-Output "$($package.Mirror) already has tag $version on $commit"
        } else {
            Write-Output "::error::$($package.Mirror) already has tag $version on $existing, not on $commit. A published version is never moved."
            $failed = $true
        }
        continue
    }
    $top = $refs.Keys | ForEach-Object { $_ -replace '^refs/tags/', '' -replace '\^\{\}$', '' } |
        Where-Object { $_ -match $VersionPattern } | ForEach-Object { [version]$_ } |
        Sort-Object | Select-Object -Last 1
    if ($null -ne $top -and $top -ge [version]$version) {
        Write-Output "::error::$version is not above $top, the highest version on $($package.Mirror)"
        $failed = $true
        continue
    }
    $pushes += [pscustomobject]@{ Mirror = $package.Mirror; Version = $version; Commit = $commit }
}
Remove-Item -Recurse -Force $work

if ($Tag -and $mirrored -notcontains $Tag) {
    Write-Output "::error::$Tag is not a release tag of a package packages.json publishes to a mirror"
    $failed = $true
}
if ($failed -eq $true) {
    Write-Output "::error::Nothing was tagged, see the errors above"
    exit 1
}

foreach ($push in $pushes) {
    if ($Push -eq $false) {
        Write-Output "Dry run - would push $($push.Commit) to $($push.Mirror) as tag $($push.Version)"
        continue
    }
    try {
        git -C $RepoName push (Get-MirrorUrl $push.Mirror) "$($push.Commit):refs/tags/$($push.Version)"
    } catch {
        Write-Output "::error::Pushing tag $($push.Version) to $($push.Mirror) failed, so the packages after it were not tagged. Re-run the job, which passes the mirrors already tagged."
        exit 1
    }
    Write-Output "Pushed $($push.Commit) to $($push.Mirror) as tag $($push.Version)"
}
exit 0
