# Tests for ci/check-environment-variable-names.ps1.
#
# Each case is one line of text and what the check must make of it. The
# digit-initial name is matched in any case, because Windows reads an
# environment variable name in any case, so a lower case old name is still
# the old name there. The fallback names keep their case, for the reason the
# check gives.

BeforeAll {
    $script:Check = Join-Path $PSScriptRoot 'check-environment-variable-names.ps1'
    . $script:Check

    function Get-Kind([string]$Line) {
        @(Find-EnvironmentNameProblem $Line | ForEach-Object { $_.Kind })
    }
}

Describe 'Find-EnvironmentNameProblem' {

    It 'finds the digit-initial name <Line>' -ForEach @(
        @{ Line = '$env:51DEGREES_DD_PATH = "file.hash"' }
        @{ Line = 'getenv("51DEGREES_IPI_PATH")' }
        @{ Line = 'getenv("51degrees_dd_path")' }
        @{ Line = '$env:51Degrees_Resource_Key = "key"' }
    ) {
        Get-Kind $Line | Should -Contain 'digit'
    }

    It 'finds the fallback name <Line>' -ForEach @(
        @{ Line = 'getenv(''RESOURCE_KEY'')' }
        @{ Line = '$_ENV["LICENSE_KEY"]' }
        @{ Line = '$_SERVER[''RESOURCE_KEY'']' }
        @{ Line = '$env:LICENSE_KEY = $Keys.TestResourceKey' }
        @{ Line = '<env name="RESOURCE_KEY" value="x"/>' }
        @{ Line = '[Environment]::SetEnvironmentVariable("LICENSE_KEY", $k)' }
        @{ Line = 'const LEGACY_RESOURCE_KEY_ENV_VAR = "RESOURCE_KEY";' }
        @{ Line = 'Constants::LEGACY_ENV_VARS' }
    ) {
        Get-Kind $Line | Should -Contain 'fallback'
    }

    # The IP Intelligence and location examples and tests read resource_key
    # as their one name today, so the fallback names keep their case.
    It 'leaves <Line> alone' -ForEach @(
        @{ Line = '$env:resource_key = $Keys.TestResourceKey' }
        @{ Line = '<env name="resource_key" value="!!YOUR_RESOURCE_KEY!!"/>' }
        @{ Line = 'getenv("_51DEGREES_RESOURCE_KEY")' }
        @{ Line = '$env:_51DEGREES_DD_PATH = "file.hash"' }
        @{ Line = '$env:_51degrees_dd_path = "file.hash"' }
        @{ Line = 'getenv("SUPER_RESOURCE_KEY")' }
        @{ Line = 'Set the 51D_ cookie for the 51Did example.' }
    ) {
        Get-Kind $Line | Should -BeNullOrEmpty
    }
}

Describe 'the check over a repository' {

    # git quotes a path holding a non-ASCII character unless told not to,
    # and a quoted path is not found on disk.
    It 'reads a file whose name is not ASCII' {
        $repo = Join-Path ([IO.Path]::GetTempPath()) ([Guid]::NewGuid())
        New-Item -ItemType Directory $repo | Out-Null
        try {
            git -C $repo init -q
            $name = 'donn' + [char]0xE9 + 'es.php'
            Set-Content -LiteralPath (Join-Path $repo $name) `
                -Value 'getenv("51DEGREES_DD_PATH");'
            git -C $repo add -A
            { & $script:Check -RepoRoot $repo } |
                Should -Throw '1 names start with a digit*'
        } finally {
            Remove-Item -Recurse -Force $repo
        }
    }
}
