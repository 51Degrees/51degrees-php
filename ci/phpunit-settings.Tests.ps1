# Tests that every PHPUnit configuration in the repository fails its run on
# a PHP error, warning, notice or deprecation, and on a risky or
# incomplete test, has PHP report every level, and loads
# tests/bootstrap.php, which fails the run on a warning, notice or
# deprecation from a PHP the tests start.
#
# The build matrix runs PHPUnit 9.6, the version common-ci installs, so a
# configuration it runs uses the PHPUnit 9.6 attributes. A package whose
# ci/run-unit-tests.ps1 runs its own vendor/bin/phpunit, as pipeline/did
# does, gets the newest PHPUnit its PHP takes, which is 10.5 or later as the
# package needs PHP 8.1, so its configuration uses the attributes of PHPUnit
# 10.5.
#
#   Invoke-Pester ci/phpunit-settings.Tests.ps1

BeforeDiscovery {
    $Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
    $Configurations = @(git -C $Root ls-files -- '*phpunit.xml' '*phpunit.xml.dist' |
        ForEach-Object {
            $script = Join-Path $Root (Split-Path $_) 'ci' 'run-unit-tests.ps1'
            @{
                Path = $_
                PhpUnit10 = (Test-Path $script) -and
                    (Select-String -Quiet -SimpleMatch 'vendor/bin/phpunit' $script)
            }
        })
}

BeforeAll {
    $script:Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
    # The copy the others are compared with.
    $script:Bootstrap = Join-Path $script:Root 'pipeline' 'core' 'tests' 'bootstrap.php'

    $script:PhpUnit9Attributes = @(
        'convertErrorsToExceptions'
        'convertNoticesToExceptions'
        'convertWarningsToExceptions'
        'convertDeprecationsToExceptions'
        'failOnWarning'
        'failOnRisky'
        'failOnIncomplete'
    )
    $script:PhpUnit10Attributes = @(
        'failOnWarning'
        'failOnNotice'
        'failOnDeprecation'
        'failOnPhpunitDeprecation'
        'failOnPhpunitWarning'
        'failOnRisky'
        'failOnIncomplete'
        'displayDetailsOnTestsThatTriggerErrors'
        'displayDetailsOnTestsThatTriggerWarnings'
        'displayDetailsOnTestsThatTriggerNotices'
        'displayDetailsOnTestsThatTriggerDeprecations'
        'displayDetailsOnPhpunitDeprecations'
    )

    function Get-Configuration([string]$Path) {
        [xml](Get-Content -Raw -LiteralPath (Join-Path $script:Root $Path))
    }
}

Describe 'PHPUnit configuration <Path>' -ForEach $Configurations {
    It 'sets each attribute that fails the run to true' {
        $phpunit = (Get-Configuration $Path).phpunit
        $names = if ($PhpUnit10) {
            $script:PhpUnit10Attributes
        } else {
            $script:PhpUnit9Attributes
        }
        foreach ($name in $names) {
            $phpunit.GetAttribute($name) | Should -Be 'true' -Because "$Path sets $name"
        }
    }

    It 'has no attribute of the other PHPUnit version' {
        $phpunit = (Get-Configuration $Path).phpunit
        $other = if ($PhpUnit10) {
            $script:PhpUnit9Attributes | Where-Object { $_ -like 'convert*' }
        } else {
            $script:PhpUnit10Attributes | Where-Object { $script:PhpUnit9Attributes -notcontains $_ }
        }
        foreach ($name in $other) {
            $phpunit.HasAttribute($name) | Should -BeFalse -Because "PHPUnit warns of $name in $Path"
        }
    }

    It 'has PHP report every level' {
        $ini = @((Get-Configuration $Path).phpunit.php.ini |
            Where-Object { $_.name -eq 'error_reporting' })
        $ini.Count | Should -Be 1
        $ini[0].value | Should -Be '-1'
    }

    It 'loads tests/bootstrap.php, the same in every package' {
        (Get-Configuration $Path).phpunit.GetAttribute('bootstrap') |
            Should -Be 'tests/bootstrap.php'
        $bootstrap = Join-Path $script:Root (Split-Path $Path) 'tests' 'bootstrap.php'
        $bootstrap | Should -Exist
        Get-Content -Raw $bootstrap |
            Should -BeExactly (Get-Content -Raw $script:Bootstrap) `
                -Because "$bootstrap is a copy of $script:Bootstrap"
    }

    It 'names the source PHPUnit 10.5 or later checks' -Skip:(-not $PhpUnit10) {
        $source = (Get-Configuration $Path).phpunit.source
        $source | Should -Not -BeNullOrEmpty
        @($source.include.directory) | Should -Contain 'src'
    }
}
