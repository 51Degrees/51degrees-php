# Tests for ci/check-test-attributes.ps1.
#
# Each case is a piece of a PHP test file and the annotations the check must
# find in it without a matching attribute.

BeforeAll {
    $script:Check = Join-Path $PSScriptRoot 'check-test-attributes.ps1'
    . $script:Check

    function Get-Missing([string]$Text) {
        @(Find-MissingTestAttribute $Text | ForEach-Object { $_.Annotation })
    }

    # Runs the check over a new repository holding one file.
    function Invoke-CheckOver([string]$Path, [string]$Text) {
        $repo = Join-Path ([IO.Path]::GetTempPath()) ([Guid]::NewGuid())
        New-Item -ItemType Directory $repo | Out-Null
        try {
            git -C $repo init -q
            $file = Join-Path $repo $Path
            New-Item -ItemType Directory (Split-Path $file) -Force | Out-Null
            Set-Content -LiteralPath $file -Value $Text
            git -C $repo add -A
            & $script:Check -RepoRoot $repo | Out-Null
            $LASTEXITCODE
        } finally {
            Remove-Item -Recurse -Force $repo
        }
    }

    $script:Uses = @'
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
'@
}

Describe 'Find-MissingTestAttribute' {

    It 'finds an annotation with no attribute: <Name>' -ForEach @(
        @{ Name = 'a class that requires Linux'; Expected = '@requires OS'; Text = @'
/**
 * @requires OS Linux
 */
class ExampleWebTests extends TestCase
'@ }
        @{ Name = 'a data provider'; Expected = '@dataProvider'; Text = @'
    /**
     * @dataProvider provider_testAcceptCH
     */
    public function testAcceptCH($value)
'@ }
        @{ Name = 'a PHP version'; Expected = '@requires PHP'; Text = @'
    /**
     * @requires PHP >= 7.2
     */
    public function testAcceptCH()
'@ }
        @{ Name = 'a method run in its own process'; Expected = '@runInSeparateProcess'; Text = @'
    /**
     * @runInSeparateProcess
     */
    public function testSend(): void
'@ }
        @{ Name = 'a hook'; Expected = '@before'; Text = @'
    /**
     * @before
     */
    protected function clear()
'@ }
    ) {
        Get-Missing ($script:Uses + "`n" + $Text) | Should -Be $Expected
    }

    It 'accepts an annotation with its imported attribute: <Name>' -ForEach @(
        @{ Name = 'a class that requires Linux'; Text = @'
/**
 * @requires OS Linux
 */
#[RequiresOperatingSystem('Linux')]
class ExampleWebTests extends TestCase
'@ }
        @{ Name = 'two attributes on two lines'; Text = @'
    /**
     * @dataProvider provider_testAcceptCH
     * @requires PHP >= 7.2
     */
    #[DataProvider('provider_testAcceptCH')]
    #[RequiresPhp('>= 7.2')]
    public function testAcceptCH($value)
'@ }
        @{ Name = 'two attributes in one group'; Text = @'
    /**
     * @before
     * @after
     */
    #[Before, After]
    protected function clear()
'@ }
        @{ Name = 'process isolation'; Text = @'
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSend(): void
'@ }
    ) {
        Get-Missing ($script:Uses + "`n" + $Text) | Should -BeNullOrEmpty
    }

    It 'accepts an attribute written with its whole namespace' {
        Get-Missing @'
namespace fiftyone\pipeline\translation\tests;

class Tests
{
    /**
     * @dataProvider provider_keySpellings
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_keySpellings')]
    public function testKey($key)
'@ | Should -BeNullOrEmpty
    }

    It 'finds an attribute this file does not import' {
        # In a namespace, an attribute that is not imported names a class in
        # that namespace, which PHPUnit does not read.
        Get-Missing @'
namespace fiftyone\pipeline\devicedetection\tests;

use PHPUnit\Framework\TestCase;

/**
 * @requires OS Linux
 */
#[RequiresOperatingSystem('Linux')]
class ExampleWebTests extends TestCase
'@ | Should -Be '@requires OS'
    }

    It 'reads only the attributes straight after the doc comment' {
        Get-Missing ($script:Uses + "`n" + @'
    /**
     * @dataProvider provider_one
     */
    public function testOne($value)
    {
    }

    #[DataProvider('provider_two')]
    public function testTwo($value)
'@) | Should -Be '@dataProvider'
    }

    It 'leaves other doc comment tags alone' {
        Get-Missing @'
    /**
     * Data Provider for testAcceptCH
     *
     * @param string $value the value
     * @return array the cases
     * @throws \Exception never
     */
    public static function provider_testAcceptCH()
'@ | Should -BeNullOrEmpty
    }

    It 'gives the line of the annotation' {
        $text = $script:Uses + "`n" + @'
/**
 * Runs only on Linux.
 *
 * @requires OS Linux
 */
class ExampleWebTests extends TestCase
'@
        $line = ($script:Uses -split "`n").Count + 4
        @(Find-MissingTestAttribute $text)[0].Line | Should -Be $line
    }
}

Describe 'check-test-attributes.ps1' {

    It 'fails on a test file with an annotation and no attribute' {
        Invoke-CheckOver 'pkg/tests/ExampleTests.php' @'
<?php
/**
 * @requires OS Linux
 */
class ExampleTests {}
'@ | Should -Be 1
    }

    It 'passes a test file with the attribute' {
        Invoke-CheckOver 'pkg/tests/ExampleTests.php' @'
<?php
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;
/**
 * @requires OS Linux
 */
#[RequiresOperatingSystem('Linux')]
class ExampleTests {}
'@ | Should -Be 0
    }

    It 'reads only files in a tests folder' {
        Invoke-CheckOver 'pkg/src/Example.php' @'
<?php
/**
 * @requires OS Linux
 */
class Example {}
'@ | Should -Be 0
    }
}
