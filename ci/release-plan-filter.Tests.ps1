# Tests for ci/release-plan-filter.ps1, with Pester 5.
#
#   Invoke-Pester ci/release-plan-filter.Tests.ps1
#
# Each test builds a throwaway git repository holding a package's
# composer.json, tags it as the last release, commits a change and asks the
# filter which files count.

BeforeAll {
    $script:Filter = Join-Path $PSScriptRoot 'release-plan-filter.ps1'
    $script:Path = 'pipeline/engines/composer.json'
    $script:Package = [pscustomobject]@{
        Name = 'fiftyone.pipeline.engines'; Path = 'pipeline/engines'
    }
    $script:From = 'fiftyone.pipeline.engines/4.5.206'

    # A manifest shaped like the packages' own.
    function New-Manifest {
        return [ordered]@{
            name = '51degrees/fiftyone.pipeline.engines'
            description = 'Engines for the 51Degrees Pipeline API.'
            keywords = @('51degrees')
            authors = @([ordered]@{
                name = '51Degrees Engineering'
                email = 'engineering@51degrees.com'
            })
            license = 'EUPL-1.2'
            type = 'library'
            homepage = 'https://example.com/engines'
            support = [ordered]@{
                source = 'https://github.com/51Degrees/51degrees-php'
                docs = 'https://example.com/docs'
            }
            require = [ordered]@{
                php = '>=7.4'
                '51degrees/fiftyone.pipeline.core' = '4.*'
            }
            autoload = [ordered]@{
                'psr-4' = [ordered]@{
                    'fiftyone\pipeline\engines\' = 'src/'
                }
            }
        }
    }

    function Write-File([string]$Repo, [string]$File, [string]$Text) {
        $full = Join-Path $Repo $File
        $null = New-Item -ItemType Directory -Force (Split-Path $full)
        Set-Content -Path $full -Value $Text -NoNewline
    }

    function Save-Commit([string]$Repo) {
        git -C $Repo add -A
        git -C $Repo -c user.name=test -c user.email=test@example.com `
            commit --quiet --allow-empty -m 'Change'
    }

    # A repository whose last release tag is on a commit holding $Before at
    # $script:Path, with a later commit holding $After there. $null for
    # either leaves the file out of that commit.
    function New-Repo($Before, $After) {
        $repo = Join-Path ([IO.Path]::GetTempPath()) `
            ([Guid]::NewGuid().ToString())
        git init --quiet $repo
        if ($null -ne $Before) {
            Write-File $repo $script:Path $Before
        }
        Write-File $repo 'pipeline/engines/src/Engine.php' '<?php'
        Save-Commit $repo
        git -C $repo tag $script:From
        $full = Join-Path $repo $script:Path
        if ($null -eq $After) {
            Remove-Item $full
        } else {
            Write-File $repo $script:Path $After
        }
        Write-File $repo 'pipeline/engines/src/Engine.php' '<?php // changed'
        Save-Commit $repo
        return $repo
    }

    function ConvertTo-Text($Manifest) {
        return $Manifest | ConvertTo-Json -Depth 10
    }

    # The files the filter counts for a change from $Before to $After.
    function Get-Counted($Before, $After,
            [string[]]$Files = @($script:Path)) {
        $script:Repo = New-Repo $Before $After
        return @(& $script:Filter -RepoPath $script:Repo `
            -Package $script:Package -From $script:From -Files $Files)
    }
}

Describe 'release-plan-filter.ps1' {

    AfterEach {
        if ($script:Repo -and (Test-Path $script:Repo)) {
            Remove-Item -Recurse -Force $script:Repo
        }
        $script:Repo = $null
    }

    Context 'a composer.json whose metadata alone changed' {
        It 'does not count a change to <Field>' -ForEach @(
            @{ Field = 'description'; Value = 'Another description.' }
            @{ Field = 'keywords'; Value = @('51degrees', 'pipeline') }
            @{ Field = 'homepage'; Value = 'https://example.com/other' }
            @{ Field = 'support'; Value = [ordered]@{
                source = 'https://github.com/51Degrees/51degrees-php'
                docs = 'https://example.com/other-docs' } }
            @{ Field = 'authors'; Value = @([ordered]@{ name = 'Someone' }) }
            @{ Field = 'license'; Value = 'MIT' }
        ) {
            $before = New-Manifest
            $after = New-Manifest
            $after[$Field] = $Value
            Get-Counted (ConvertTo-Text $before) (ConvertTo-Text $after) |
                Should -BeNullOrEmpty
        }

        It 'does not count every metadata field changed at once' {
            $after = New-Manifest
            $after.description = 'Another description.'
            $after.keywords = @('pipeline')
            $after.homepage = 'https://example.com/other'
            $after.support.docs = 'https://example.com/other-docs'
            $after.authors[0].email = 'someone@example.com'
            $after.license = 'MIT'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) |
                Should -BeNullOrEmpty
        }

        It 'does not count a metadata field that was added' {
            $before = New-Manifest
            $before.Remove('keywords')
            Get-Counted (ConvertTo-Text $before) (ConvertTo-Text (New-Manifest)) |
                Should -BeNullOrEmpty
        }

        It 'does not count a change to the layout alone' {
            $text = ConvertTo-Text (New-Manifest)
            Get-Counted $text ($text -replace '  ', '    ') |
                Should -BeNullOrEmpty
        }
    }

    Context 'a composer.json whose other fields changed' {
        It 'counts a changed requirement' {
            $after = New-Manifest
            $after.require['51degrees/fiftyone.pipeline.core'] = '^4.5.207'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) |
                Should -Be @($script:Path)
        }

        It 'counts an added requirement' {
            $after = New-Manifest
            $after.require['ext-json'] = '*'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) |
                Should -Be @($script:Path)
        }

        It 'counts a changed autoload' {
            $after = New-Manifest
            $after.autoload.'psr-4'.'fiftyone\pipeline\engines\' = 'lib/'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) |
                Should -Be @($script:Path)
        }

        It 'counts an added version' {
            $after = New-Manifest
            $after.version = '4.5.207'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) |
                Should -Be @($script:Path)
        }

        It 'counts a value whose case alone changed' {
            $after = New-Manifest
            $after.type = 'Library'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) |
                Should -Be @($script:Path)
        }

        It 'counts a requirement changed with the metadata' {
            $after = New-Manifest
            $after.description = 'Another description.'
            $after.require.php = '>=8.1'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) |
                Should -Be @($script:Path)
        }
    }

    Context 'a composer.json that cannot be compared' {
        It 'counts one that was added' {
            Get-Counted $null (ConvertTo-Text (New-Manifest)) |
                Should -Be @($script:Path)
        }

        It 'counts one that was removed' {
            Get-Counted (ConvertTo-Text (New-Manifest)) $null |
                Should -Be @($script:Path)
        }

        It 'counts one that is not JSON' {
            Get-Counted (ConvertTo-Text (New-Manifest)) '{ "name": ' |
                Should -Be @($script:Path)
        }
    }

    Context 'other files' {
        It 'counts every file that is not a composer.json' {
            $files = @('pipeline/engines/src/Engine.php',
                'pipeline/engines/composer.lock')
            Get-Counted (ConvertTo-Text (New-Manifest)) `
                (ConvertTo-Text (New-Manifest)) $files |
                Should -Be $files
        }

        It 'keeps the other files when the composer.json does not count' {
            $after = New-Manifest
            $after.description = 'Another description.'
            Get-Counted (ConvertTo-Text (New-Manifest)) (ConvertTo-Text $after) `
                @($script:Path, 'pipeline/engines/src/Engine.php') |
                Should -Be @('pipeline/engines/src/Engine.php')
        }

        It 'counts nothing when given no files' {
            Get-Counted (ConvertTo-Text (New-Manifest)) `
                (ConvertTo-Text (New-Manifest)) @() |
                Should -BeNullOrEmpty
        }
    }
}
