# Shared by the scripts in this folder, which dot-source it. Paths are
# relative to the repository root.
#
# common-ci calls the scripts in this folder once for the whole repository,
# from the root of its own checkout, with -RepoName set to the folder this
# repository is cloned into. Most of them run the script of the same name in
# each package's own ci folder, with -RepoName set to "<repository>/<package>".
# The package scripts were written for the old repositories and are
# published through the mirrors as they are, so they are run unchanged.
#
# Scripts that are the same in every package (setup-environment,
# build-package-requirements, build-package, publish-package) are not run per
# package. The root script calls common-ci once instead.
#
# The packages are listed in packages.json, each after the packages it
# requires. Name, Path, DependsOn and Publish are what common-ci's
# steps/release-plan.ps1 reads to work out which packages a publish releases,
# and at which version. Fields:
#   Name     The package's Composer name without "51degrees/". Its releases
#            are tagged "<Name>/<version>" here. device-detection/onpremise
#            has the same Composer name as device-detection/cloud and is
#            never published, so it has a name of its own.
#   Path     The package directory.
#   DependsOn  The Names of the packages here that its composer.json
#            requires. The published composer.json requires each of them at
#            the version released with it, see Get-PublishedComposerJson.
#   Publish  Whether the package is released. Only a package with a mirror
#            can be, because Packagist reads the mirrors.
#   Mirror   The old repository Packagist reads, now a read-only mirror of
#            this directory, kept equal to it by .github/workflows/mirror.yml.
#            null for a package that has no mirror.
#   Vendor   Where the package's tests get their dependencies.
#            Root: from the root vendor folder, which the root composer.json
#            fills through path repositories, so the tests run against the
#            source of the other packages here and not against the copies on
#            Packagist. build-project.ps1 gives the package a vendor/autoload.php
#            that loads the root one, which is what its phpunit.xml bootstraps.
#            The package's own build-project.ps1 and install-package.ps1 are
#            not run, as they would run composer install in the package.
#            Own: the package's own build-project.ps1 runs composer install in
#            the package directory, from a copy of its composer.json with path
#            repositories for the pipeline packages, so its tests also run
#            against their source here. Used where the package is not in the
#            root composer.json, or where its build script also compiles a
#            native extension and cannot be split from its composer install.
#   Ci       Whether the package has a ci folder to run scripts from.
#   Assembled  The mirror's version tags go on a commit that
#            .github/workflows/did-publish.yml assembles, not on the commit
#            that mirror.yml tags. The mirror's main is the plain split
#            either way.
#   Native   The package builds a PHP extension, which its scripts build
#            with phpize, configure and make. Their old matrices never had a
#            Windows image, so the package is skipped on Windows.
#
# A package is also skipped on a leg whose PHP is below the "php" constraint
# in the package's composer.json. On such a leg it is left out of the root
# composer install too, so the rest can install. For example, on PHP 7.4
# pipeline/did (>=8.1), pipeline/translation, ip-intelligence and
# device-detection/onpremise (>=8.0) are skipped.
#
# Per package:
#   pipeline/core, engines and cloudrequestengine: Unit suite. Only
#     cloudrequestengine has integration tests, which need TestResourceKey.
#     No performance tests.
#   pipeline/did: Unit suite with its own vendor/bin/phpunit, which here
#     is the root one. Its Live suite is not run by its CI, and its tags are
#     published by .github/workflows/did-publish.yml, because the published
#     tree has the OWID source copied in.
#   pipeline/translation: Unit suite. No mirror, and its code is also
#     tested through ip-intelligence, which requires it.
#   pipeline/derivedproperty: Unit suite, which runs the shared cases from
#     the derived-properties submodule at the root. No mirror.
#   device-detection/cloud: Unit suite without keys, Integration suite and the
#     Selenium contract tests against the cloud example when TestResourceKey
#     and the CSV file are present.
#   device-detection/onpremise: Own. Not in the root composer.json, as it
#     has the same package name as device-detection/cloud. Builds the native
#     extension, needs the TAC data file for its tests, and is the only
#     package with real performance tests. No mirror.
#   location: Integration suite only, which needs TestResourceKey.
#   ip-intelligence: Own, because its build-project.ps1 compiles the
#     native extension and then runs composer install. No mirror yet.
#
# Moving the Own packages to Root needs their native build split from their
# composer install.
$Packages = @(Get-Content -Raw (Join-Path $PSScriptRoot 'packages.json') |
    ConvertFrom-Json)

# TEMPORARY, on this tmp branch only, for the dry run. Each published package
# last released 4.5.207, from 59bb0081. Those "<package>/4.5.207" tags are not
# pushed yet, so they are made here, in this checkout only, before any plan is
# read. Nothing pushes them.
foreach ($tmpPackage in $Packages | Where-Object Publish) {
    $tmpTag = "$($tmpPackage.Name)/4.5.207"
    if (-not (& git -C (Join-Path $PSScriptRoot '..') tag --list $tmpTag)) {
        & git -C (Join-Path $PSScriptRoot '..') tag $tmpTag 59bb0081cff9c9c83b44e87aa1dd36e9b39b136d
        Write-Host "Temporary: made $tmpTag on 59bb0081 in this checkout only"
    }
}

# Where common-ci's steps/release-plan.ps1 is looked for. common-ci runs these
# scripts from its own root, with this repository checked out inside it. A
# checkout of common-ci beside this repository, or one named by COMMON_CI, is
# found too.
$CommonCiFolders = @($env:COMMON_CI, (Join-Path $PSScriptRoot '..' '..'),
    (Join-Path $PSScriptRoot '..' '..' 'common-ci'))

# The path of common-ci's steps/release-plan.ps1, which the scripts that work
# out a release dot-source. Only they need common-ci, so the mirror workflows
# read the packages from this file without it.
function Get-ReleasePlanScript {
    $found = $CommonCiFolders | Where-Object { $_ } |
        ForEach-Object { Join-Path $_ 'steps' 'release-plan.ps1' } |
        Where-Object { Test-Path $_ } |
        Select-Object -First 1
    if ($null -eq $found) {
        throw "common-ci's steps/release-plan.ps1 was not found. Set " +
            "COMMON_CI to a checkout of common-ci."
    }
    return $found
}

# The highest version tagged on a package's mirror, which is the highest
# version published, or $null when it has none. The mirrors are public, so no
# token is needed.
function Get-MirrorHighestVersion ([string]$Mirror, [string]$Owner = '51Degrees') {
    $lines = @(& git ls-remote --tags "https://github.com/$Owner/$Mirror.git")
    if ($LASTEXITCODE -ne 0) {
        throw "The tags of $Owner/$Mirror could not be read"
    }
    # An annotated tag is listed twice, the second time ending ^{}.
    $versions = foreach ($line in $lines) {
        $name = ($line -split "`t")[1] -replace '^refs/tags/', '' -replace '\^\{\}$', ''
        if ($name -match '^\d+\.\d+\.\d+$') {
            [version]$name
        }
    }
    return $versions | Sort-Object | Select-Object -Last 1
}

# The text of the composer.json a package is published with. It is the
# package's own, with each requirement on another package here set to
# "^<version>", where $Versions gives the version by Name. Only those values
# are edited, in the text, so the published file differs in nothing else.
# Throws when the requirements and DependsOn disagree, because a requirement
# left as a range lets Composer pair the package with releases it was not
# released with.
function Get-PublishedComposerJson {
    param (
        [Parameter(Mandatory)][string]$RepoName,
        [Parameter(Mandatory)]$Package,
        [Parameter(Mandatory)][hashtable]$Versions
    )
    $path = Join-Path $RepoName $Package.Path 'composer.json'
    $text = Get-Content -Raw $path
    $wanted = [ordered]@{}
    foreach ($name in @($Package.DependsOn)) {
        $dependency = $Packages | Where-Object Name -eq $name
        if ($null -eq $dependency -or $dependency.Publish -ne $true) {
            throw "$($Package.Name) depends on $name, which packages.json does not publish"
        }
        if ($Versions.ContainsKey($name) -eq $false) {
            throw "No version of $name was given for the requirement of $($Package.Name)"
        }
        $composerName = (Get-Content -Raw (Join-Path $RepoName $dependency.Path 'composer.json') |
            ConvertFrom-Json).name
        $wanted[$composerName] = "^$($Versions[$name])"
    }

    $parsed = $text | ConvertFrom-Json -AsHashtable
    $own = @($parsed.require.Keys | Where-Object { $_ -like '51degrees/*' })
    foreach ($key in $own) {
        if ($wanted.Contains($key) -eq $false) {
            throw "$path requires $key, which the DependsOn of $($Package.Name) in packages.json does not list"
        }
    }
    foreach ($key in $wanted.Keys) {
        if ($own -notcontains $key) {
            throw "The DependsOn of $($Package.Name) in packages.json gives $key, which $path does not require"
        }
    }
    if ($wanted.Count -eq 0) {
        return $text
    }

    $block = [regex]::Match($text, '"require"\s*:\s*\{[^{}]*\}')
    if ($block.Success -eq $false) {
        throw "The require section of $path was not found"
    }
    $require = $block.Value
    foreach ($key in $wanted.Keys) {
        $require = [regex]::Replace($require,
            '("' + [regex]::Escape($key) + '"\s*:\s*)"[^"]*"',
            '${1}"' + $wanted[$key] + '"')
    }
    $published = $text.Substring(0, $block.Index) + $require +
        $text.Substring($block.Index + $block.Length)

    # The edit must change those values and nothing else.
    foreach ($key in $wanted.Keys) {
        $parsed.require[$key] = $wanted[$key]
    }
    $expected = $parsed | ConvertTo-Json -Depth 64 -Compress
    $actual = $published | ConvertFrom-Json -AsHashtable |
        ConvertTo-Json -Depth 64 -Compress
    if ($actual -ne $expected) {
        throw "Raising the requirements in $path changed more than their versions"
    }
    return $published
}

# The PHP version of this leg, read from the php on the PATH rather than from
# the options, because the version the runner really has is what the
# packages run on. $null when there is no php, as in the job that prefetches
# assets, and then no package is skipped for its PHP version.
function Get-PhpVersion {
    if ($null -eq $script:PhpVersion) {
        $script:PhpVersion = ''
        if (Get-Command php -ErrorAction SilentlyContinue) {
            $text = php -r 'echo PHP_VERSION;'
            if ($text -match '^(\d+)\.(\d+)\.(\d+)') {
                $script:PhpVersion = [version]"$($Matches[1]).$($Matches[2]).$($Matches[3])"
            }
        }
    }
    return $script:PhpVersion ? $script:PhpVersion : $null
}

# Why the package cannot run on this leg, or $null when it can.
#
# Only a constraint of the form ">=X.Y" or ">=X.Y.Z" is understood, which is
# what every package declares. Any other form throws rather than guessing, so
# a package that adopts one fails loudly and this is extended then.
function Get-SkipReason ([string]$RepoName, $Package) {
    if ($Package.Native -and $IsWindows) {
        return 'it builds a PHP extension, which is not built on Windows'
    }
    $composer = Join-Path $RepoName $Package.Path 'composer.json'
    $constraint = (Get-Content -Raw $composer | ConvertFrom-Json).require.php
    $php = Get-PhpVersion
    if (-not $constraint -or -not $php) { return $null }
    if ($constraint -notmatch '^>=\s*(\d+\.\d+(\.\d+)?)$') {
        throw "$composer requires php '$constraint', and ci/packages.ps1 only understands '>=X.Y'"
    }
    if ($php -lt [version]$Matches[1]) {
        return "it requires php $constraint and this leg has $php"
    }
    return $null
}

# The root composer install. On a leg where a package in the root
# composer.json is skipped, it installs from composer.leg.json instead, which
# is composer.json without that package in require. The path repositories
# stay, so a package that is still installed and requires a skipped one still
# resolves it.
function Install-RootComposer ([string]$RepoName) {
    $root = Get-Content -Raw (Join-Path $RepoName 'composer.json') | ConvertFrom-Json -AsHashtable
    $inRoot = @($root.repositories | ForEach-Object { $_.url -replace '^\./', '' })
    $left = @()
    foreach ($package in $Packages) {
        if ($package.Path -notin $inRoot) { continue }
        $reason = Get-SkipReason $RepoName $package
        if (-not $reason) { continue }
        $name = (Get-Content -Raw (Join-Path $RepoName $package.Path 'composer.json') | ConvertFrom-Json).name
        if ($root.require.ContainsKey($name)) {
            $root.require.Remove($name)
            $left += $package.Path
            Write-Host "Leaving $($package.Path) out of the root composer install: $reason"
        }
    }
    if (-not $left) {
        ./php/build-project.ps1 -RepoName $RepoName
        return
    }
    $root | ConvertTo-Json -Depth 20 | Set-Content (Join-Path $RepoName 'composer.leg.json')
    $env:COMPOSER = 'composer.leg.json'
    try {
        ./php/build-project.ps1 -RepoName $RepoName
    } finally {
        Remove-Item env:COMPOSER
    }
}

$RootAutoloadMarker = 'Loads the root autoloader'

# Gives each Root package a vendor folder whose autoload.php and bin/phpunit
# load the root ones. A link to the root vendor folder would be shorter, but
# the device-detection/cloud Selenium step runs composer install in its package,
# and through a link that would replace the root install for every package
# tested after it. A folder holding only these two files is replaced by a full
# install and leaves the root alone.
function Set-RootAutoload ([string]$RepoName) {
    foreach ($package in $Packages | Where-Object { $_.Vendor -eq 'Root' -and $_.Ci }) {
        if (Get-SkipReason $RepoName $package) { continue }
        $vendor = Join-Path $RepoName $package.Path 'vendor'
        $autoload = Join-Path $vendor 'autoload.php'
        if ((Test-Path $vendor) -and -not ((Test-Path $autoload) -and
            (Select-String -Quiet -SimpleMatch $RootAutoloadMarker $autoload))) {
            throw "$vendor is a composer install of its own. Remove it, or the tests of $($package.Path) use it instead of the packages in this repository."
        }
        New-Item -ItemType Directory -Force -Path (Join-Path $vendor 'bin') | Out-Null
        # The package sits one or two folders below the root, so the way up
        # from its vendor folder depends on its path.
        $up = '../' * ($package.Path.Split('/').Count + 1)
        "<?php`n// $RootAutoloadMarker, written by ci/build-project.ps1.`nreturn require __DIR__ . '/$($up)vendor/autoload.php';`n" |
            Set-Content -NoNewline $autoload
        "<?php`n// $RootAutoloadMarker, written by ci/build-project.ps1.`nrequire __DIR__ . '/$($up)../vendor/bin/phpunit';`n" |
            Set-Content -NoNewline (Join-Path $vendor 'bin' 'phpunit')
    }
}

# Writes composer.ci.json in a package, a copy of its composer.json with path
# repositories for the named pipeline packages. A composer install there with
# $env:COMPOSER set to composer.ci.json then takes those packages from this
# checkout rather than Packagist, as the Own packages' build scripts do.
function Write-CheckoutComposer ([string]$RepoName, [string]$Package, [string[]]$Pipeline) {
    $up = '../' * $Package.Split('/').Count
    $composer = Get-Content -Raw (Join-Path $RepoName $Package 'composer.json') |
        ConvertFrom-Json -AsHashtable
    $composer['repositories'] = @(foreach ($name in $Pipeline) {
        @{ type = 'path'; url = "$($up)pipeline/$name"; options = @{ symlink = $true;
            versions = @{ "51degrees/fiftyone.pipeline.$name" = '4.x-dev' } } }
    })
    $composer['minimum-stability'] = 'dev'
    $composer['prefer-stable'] = $true
    $composer | ConvertTo-Json -Depth 20 |
        Set-Content (Join-Path $RepoName $Package 'composer.ci.json')
}

# Runs ci/<Script>.ps1 of each package that has it, with -RepoName set to the
# package directory and any of $Parameters the script accepts, the way
# common-ci's steps/run-script.ps1 does. Every package runs even when one
# fails, so one run shows all the failures, and this throws at the end.
#
# The package scripts write their test results under the package directory.
# common-ci collects them from <repository>/test-results, so they are moved
# there after each script.
#
# $Environment maps a package path to environment variables that are set
# while that package's script runs, and restored after it.
function Invoke-PackageScript {
    param (
        [Parameter(Mandatory)][string]$RepoName,
        [Parameter(Mandatory)][string]$Script,
        [hashtable]$Parameters = @{},
        [string]$Vendor,
        [hashtable]$Environment = @{}
    )
    $failed = @()
    foreach ($package in $Packages) {
        if ($Vendor -and $package.Vendor -ne $Vendor) { continue }
        $reason = Get-SkipReason $RepoName $package
        if ($reason) {
            Write-Host "Skipping $($package.Path) for $Script, because $reason"
            continue
        }
        $path = Join-Path $RepoName $package.Path 'ci' "$Script.ps1"
        if (-not (Test-Path $path)) {
            Write-Host "$($package.Path) has no ci/$Script.ps1, skipping"
            continue
        }
        $path = (Resolve-Path $path).Path
        $accepted = (Get-Command $path).Parameters
        $arguments = @{}
        foreach ($parameter in $Parameters.GetEnumerator()) {
            if ($accepted.ContainsKey($parameter.Key) -and $null -ne $parameter.Value) {
                $arguments[$parameter.Key] = $parameter.Value
            }
        }
        if ($accepted.ContainsKey('RepoName')) {
            $arguments.RepoName = "$RepoName/$($package.Path)"
        }

        $variables = $Environment[$package.Path] ?? @{}
        $saved = @{}
        foreach ($name in $variables.Keys) {
            $saved[$name] = [Environment]::GetEnvironmentVariable($name)
            [Environment]::SetEnvironmentVariable($name, $variables[$name])
        }

        Write-Host "::group::$($package.Path): $Script"
        try {
            $global:LASTEXITCODE = 0
            & $path @arguments
            if ($LASTEXITCODE -ne 0) {
                throw "exited with code $LASTEXITCODE"
            }
        } catch {
            Write-Host "::error title=$Script failed::$($package.Path): $_"
            $failed += $package.Path
        } finally {
            foreach ($name in $saved.Keys) {
                [Environment]::SetEnvironmentVariable($name, $saved[$name])
            }
            Move-TestResults -RepoName $RepoName -Package $package.Path
            Write-Host "::endgroup::"
        }
    }
    if ($failed) {
        throw "$Script failed for $($failed -join ', ')"
    }
}

function Move-TestResults ([string]$RepoName, [string]$Package) {
    $from = Join-Path $RepoName $Package 'test-results'
    if (-not (Test-Path $from)) { return }
    $from = (Resolve-Path $from).Path
    $to = Join-Path $RepoName 'test-results'
    foreach ($file in Get-ChildItem -Recurse -File $from) {
        $target = Join-Path $to ([IO.Path]::GetRelativePath($from, $file.FullName))
        New-Item -ItemType Directory -Force -Path (Split-Path $target) | Out-Null
        Move-Item -Force $file.FullName $target
    }
}
