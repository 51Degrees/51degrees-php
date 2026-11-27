# Tests ci/check-error-control.php, which fails when a PHP file uses the @
# error control operator, and runs it over the repository.
#
#   Invoke-Pester ci/check-error-control.Tests.ps1

BeforeAll {
    $script:Check = Join-Path $PSScriptRoot 'check-error-control.php'

    function Invoke-Check ([string[]]$Files) {
        $output = & php $script:Check @Files 2>&1
        return @{ Status = $LASTEXITCODE; Output = ($output -join "`n") }
    }

    function New-PhpFile ([string]$Name, [string]$Text) {
        $path = Join-Path $TestDrive $Name
        Set-Content -NoNewline -Path $path -Value $Text
        return $path
    }
}

Describe 'check-error-control.php' {
    It 'passes the repository' {
        $result = Invoke-Check @()
        $result.Output | Should -Match 'No PHP file uses the @ operator'
        $result.Status | Should -Be 0
    }

    It 'fails a call made with @, naming the file and line' {
        $file = New-PhpFile 'call.php' "<?php`n`$a = 1;`n`$b = @file_get_contents('x');`n"
        $result = Invoke-Check @($file)
        $result.Status | Should -Be 1
        $result.Output | Should -Match ([regex]::Escape("call.php:3"))
    }

    It 'fails @ before a variable' {
        $file = New-PhpFile 'variable.php' "<?php`n`$b = @`$a['x'];`n"
        (Invoke-Check @($file)).Status | Should -Be 1
    }

    It 'passes @ in a comment, a string, an annotation and an email address' {
        $file = New-PhpFile 'text.php' ("<?php`n" +
            "// write to dev@example.com`n" +
            "/** @param string `$a @return int */`n" +
            "function f(`$a) { return strlen('@' . `"@x`" . `$a); }`n" +
            "#[Attribute] class A {}`n" +
            "?>`nmail@example.com @ in HTML`n")
        $result = Invoke-Check @($file)
        $result.Output | Should -Match 'No PHP file uses'
        $result.Status | Should -Be 0
    }
}
