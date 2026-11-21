# Tests for ci/publish-package.ps1, with Pester 5.
#
#   Invoke-Pester ci/publish-package.Tests.ps1
#
# A GitHub release attaches exactly the files a package registry serves for
# that version, and in the call that creates the release, because the
# SourceForge project's Files sync from the GitHub releases and ignore files
# added after a release is published. Packagist serves GitHub's archive of
# the commit tagged on the package's mirror, so each release attaches a zip
# of that commit.
#
# Each test builds a throwaway repository with the packages' folders, a bare
# origin to push the tags to, and a stand-in for common-ci's release plan,
# then runs the publish with a stand-in for gh that records each call and
# opens each zip it is given.

BeforeAll {
    $script:Publish = Join-Path $PSScriptRoot 'publish-package.ps1'
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    function Write-File([string]$Repo, [string]$File, [string]$Text) {
        $full = Join-Path $Repo $File
        $null = New-Item -ItemType Directory -Force (Split-Path $full)
        [IO.File]::WriteAllText($full, $Text)
    }

    function Save-Commit([string]$Repo, [string]$Message) {
        git -C $Repo add -A
        git -C $Repo -c user.name=test -c user.email=test@example.com `
            commit --quiet -m $Message
    }

    function Get-Manifest([string]$Name, [hashtable]$Require) {
        $manifest = [ordered]@{
            name = "51degrees/$Name"
            description = "$Name for the tests."
            license = 'EUPL-1.2'
            type = 'library'
            require = [ordered]@{ php = '>=7.4' }
        }
        foreach ($key in $Require.Keys) {
            $manifest.require[$key] = $Require[$key]
        }
        return $manifest | ConvertTo-Json -Depth 10
    }

    # The repository, as common-ci lays it out: a folder named "repo" in the
    # current folder, with the plan script in a folder named by COMMON_CI.
    function New-Layout {
        $root = Join-Path ([IO.Path]::GetTempPath()) ([Guid]::NewGuid())
        $repo = Join-Path $root 'repo'
        $origin = Join-Path $root 'origin.git'
        git init --quiet --bare $origin
        git init --quiet $repo
        git -C $repo remote add origin $origin

        Write-File $repo 'README.md' 'Not part of any package.'
        Write-File $repo 'ci/packages.json' `
            (Get-Content -Raw (Join-Path $PSScriptRoot 'packages.json'))
        Write-File $repo 'pipeline/core/composer.json' `
            (Get-Manifest 'fiftyone.pipeline.core' @{})
        Write-File $repo 'pipeline/core/src/Core.php' '<?php // core'
        Write-File $repo 'pipeline/engines/composer.json' `
            (Get-Manifest 'fiftyone.pipeline.engines' @{
                '51degrees/fiftyone.pipeline.core' = '4.*' })
        Write-File $repo 'pipeline/engines/src/Engine.php' '<?php // engines'
        Write-File $repo 'pipeline/did/composer.json' `
            (Get-Manifest 'fiftyone.pipeline.did' @{})
        Write-File $repo 'pipeline/did/src/Did.php' '<?php // did'
        # Stands in for the script that builds the published did package.
        Write-File $repo 'pipeline/did/ci/assemble-package.sh' @'
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
rm -rf "$1"
mkdir -p "$1/third-party"
cp -R composer.json src "$1/"
echo 'copied in' > "$1/third-party/owid.txt"
'@
        Save-Commit $repo 'Start'
        foreach ($name in 'fiftyone.pipeline.core', 'fiftyone.pipeline.engines',
                'fiftyone.pipeline.did') {
            git -C $repo tag "$name/4.5.207"
        }
        Write-File $repo 'pipeline/core/src/Core.php' '<?php // core changed'
        Write-File $repo 'pipeline/did/src/Did.php' '<?php // did changed'
        Save-Commit $repo 'Change'

        # A replace ref, which the publish takes as ci/prepare-split.sh
        # having run. The real list of grafts names commits of the real
        # repository, which this one does not have.
        $one = 'one' | git -C $repo hash-object -w --stdin
        $two = 'two' | git -C $repo hash-object -w --stdin
        git -C $repo replace $one $two

        $commonCi = Join-Path $root 'common-ci'
        Write-File $commonCi 'steps/release-plan.ps1' @'
function Get-ReleasePlan([string]$RepoName) {
    return $global:TestPlan
}

# As common-ci's own.
function Get-Packages([string]$RepoPath) {
    return Get-Content -Raw (Join-Path $RepoPath 'ci/packages.json') |
        ConvertFrom-Json
}

function Get-PackagePaths($Package) {
    $paths = @($Package.Path)
    if ($Package.ExtraPaths) {
        $paths += $Package.ExtraPaths
    }
    return $paths
}
'@
        return [pscustomobject]@{ Root = $root; Repo = $repo; CommonCi = $commonCi }
    }

    function New-Release([string]$Name, [string]$Reason) {
        return [pscustomobject]@{
            Name = $Name; Previous = '4.5.207'; Version = '4.5.208'
            Reason = $Reason
        }
    }

    # Runs the publish over the layout and returns what gh was asked to do.
    function Invoke-Publish($Layout, [bool]$DryRun = $false) {
        $global:GhCalls = [Collections.Generic.List[object]]::new()
        $env:COMMON_CI = $Layout.CommonCi
        Push-Location $Layout.Root
        try {
            $script:Output = @(& $script:Publish -RepoName 'repo' `
                -OrgName '51Degrees' -DryRun $DryRun)
        } finally {
            Pop-Location
            Remove-Item env:COMMON_CI
        }
        return $global:GhCalls
    }

    # The zip files named in one call, each with the text of every entry,
    # read when the call is made.
    function Read-Zips([object[]]$Arguments) {
        $zips = [ordered]@{}
        foreach ($argument in $Arguments) {
            if ("$argument" -like '*.zip') {
                $entries = [ordered]@{}
                $zip = [IO.Compression.ZipFile]::OpenRead("$argument")
                try {
                    foreach ($entry in $zip.Entries) {
                        if ($entry.FullName.EndsWith('/')) { continue }
                        $reader = [IO.StreamReader]::new($entry.Open())
                        $entries[$entry.FullName] = $reader.ReadToEnd()
                        $reader.Dispose()
                    }
                } finally {
                    $zip.Dispose()
                }
                $zips[(Split-Path -Leaf "$argument")] = $entries
            }
        }
        return $zips
    }
}

Describe 'publish-package.ps1' {

    BeforeEach {
        $script:Layout = New-Layout
        # Records each call, and reads each zip while it is there.
        function global:gh {
            $global:GhCalls.Add([pscustomobject]@{
                Arguments = @($args)
                Zips = Read-Zips $args
            })
        }
    }

    AfterEach {
        Remove-Item function:global:gh
        Remove-Item -Recurse -Force $script:Layout.Root
        $global:TestPlan = $null
    }

    It 'creates each release with its zip in the same call' {
        $global:TestPlan = @(
            New-Release 'fiftyone.pipeline.core' 'changed since 4.5.207 (1 file)'
            New-Release 'fiftyone.pipeline.engines' 'a package it depends on is released'
        )
        $calls = Invoke-Publish $script:Layout
        $calls.Count | Should -Be 2
        $calls[0].Arguments[2..4] | Should -Be @('release', 'create',
            'fiftyone.pipeline.core/4.5.208')
        @($calls[0].Zips.Keys) | Should -Be @('pipeline-php-core-4.5.208.zip')
        $calls[1].Arguments[4] | Should -Be 'fiftyone.pipeline.engines/4.5.208'
        @($calls[1].Zips.Keys) | Should -Be @('pipeline-php-engines-4.5.208.zip')
    }

    It 'zips the package folder alone, in one folder named for the mirror and version' {
        $global:TestPlan = @(
            New-Release 'fiftyone.pipeline.core' 'changed since 4.5.207 (1 file)'
        )
        $calls = Invoke-Publish $script:Layout
        $entries = $calls[0].Zips['pipeline-php-core-4.5.208.zip']
        @($entries.Keys | Sort-Object) | Should -Be @(
            'pipeline-php-core-4.5.208/composer.json',
            'pipeline-php-core-4.5.208/src/Core.php')
        $entries['pipeline-php-core-4.5.208/src/Core.php'] |
            Should -Be '<?php // core changed'
    }

    It 'zips the composer.json the mirror is tagged with, requiring the versions released with it' {
        $global:TestPlan = @(
            New-Release 'fiftyone.pipeline.core' 'changed since 4.5.207 (1 file)'
            New-Release 'fiftyone.pipeline.engines' 'a package it depends on is released'
        )
        $calls = Invoke-Publish $script:Layout
        $text = $calls[1].Zips['pipeline-php-engines-4.5.208.zip'][
            'pipeline-php-engines-4.5.208/composer.json']
        ($text | ConvertFrom-Json).require.'51degrees/fiftyone.pipeline.core' |
            Should -Be '^4.5.208'
    }

    It 'zips the assembled package for a package whose mirror tag is assembled' {
        $global:TestPlan = @(
            New-Release 'fiftyone.pipeline.did' 'changed since 4.5.207 (1 file)'
        )
        $calls = Invoke-Publish $script:Layout
        $entries = $calls[0].Zips['pipeline-php-did-4.5.208.zip']
        @($entries.Keys | Sort-Object) | Should -Be @(
            'pipeline-php-did-4.5.208/composer.json',
            'pipeline-php-did-4.5.208/src/Did.php',
            'pipeline-php-did-4.5.208/third-party/owid.txt')
    }

    It 'builds each zip in a dry run, and makes no release' {
        $global:TestPlan = @(
            New-Release 'fiftyone.pipeline.core' 'changed since 4.5.207 (1 file)'
        )
        $calls = Invoke-Publish $script:Layout -DryRun $true
        $calls.Count | Should -Be 0
        ($script:Output -join "`n") | Should -Match 'pipeline-php-core-4\.5\.208\.zip'
    }
}
