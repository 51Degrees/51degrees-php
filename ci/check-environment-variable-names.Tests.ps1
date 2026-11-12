# Tests for ci/check-environment-variable-names.ps1.
#
# Each case is one line of text and what the check must make of it. The
# names are matched in any case, because Windows reads an environment
# variable name in any case, so a lower case old name is still the old name
# there. The old names kept their case while resource_key was read as a
# variable, and that name has gone, so they are matched in any case now too.

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
        @{ Line = '${env:LICENSE_KEY} = $Keys.TestResourceKey' }
        @{ Line = '${Env:RESOURCE_KEY}' }
        @{ Line = 'Remove-Item Env:\LICENSE_KEY' }
        @{ Line = '          url: ${{ env.CLOUD_ENDPOINT }}' }
        @{ Line = '<env name="RESOURCE_KEY" value="x"/>' }
        @{ Line = '[Environment]::SetEnvironmentVariable("LICENSE_KEY", $k)' }
        @{ Line = 'const LEGACY_RESOURCE_KEY_ENV_VAR = "RESOURCE_KEY";' }
        @{ Line = 'Constants::LEGACY_ENV_VARS' }
        @{ Line = '$env:resource_key = $Keys.TestResourceKey' }
        @{ Line = '<env name="resource_key" value="!!YOUR_RESOURCE_KEY!!"/>' }
        @{ Line = 'getenv(''license_key'')' }
        @{ Line = 'public const RESOURCE_KEY_ENV_VAR = ''resource_key'';' }
        @{ Line = 'const ENDPOINT_ENV_VAR = ''cloud_endpoint'';' }
        @{ Line = '$env:cloud_endpoint = "https://cloud.51degrees.com/api/v4/"' }
        @{ Line = '<env name="IPINTELLIGENCEDATAFILE" value=""/>' }
        @{ Line = '$env:FIFTYONE_DATA_FILE = "$PWD/assets/TAC-HashV41.hash"' }
        @{ Line = 'getenv(''FIFTYONE_UA_CSV'')' }
    ) {
        Get-Kind $Line | Should -Contain 'fallback'
    }

    # RESOURCE_KEY and CLOUD_ENDPOINT also name things that are not
    # environment variables, being the resource_key query string parameter
    # and the cloud_endpoint pipeline setting, so those are matched only
    # where a variable is read, set or named in a constant.
    It 'leaves <Line> alone' -ForEach @(
        @{ Line = 'public const RESOURCE_KEY_QUERY_PARAM = ''resource_key'';' }
        @{ Line = 'public const resourceKey = ''resource_key'';' }
        @{ Line = '$params = [''resourceKey'' => ''resource_key''];' }
        @{ Line = '$settings["cloud_endpoint"] = $endpoint;' }
        @{ Line = 'getenv("_51DEGREES_RESOURCE_KEY")' }
        @{ Line = 'getenv("_51DEGREES_CLOUD_ENDPOINT")' }
        @{ Line = '$env:_51DEGREES_DD_PATH = "file.hash"' }
        @{ Line = '$env:_51degrees_dd_path = "file.hash"' }
        @{ Line = '${env:_51DEGREES_RESOURCE_KEY} = $Keys.TestResourceKey' }
        @{ Line = '  PAID_RESOURCE_KEY: ${{ env.PAID_RESOURCE_KEY }}' }
        @{ Line = 'getenv("SUPER_RESOURCE_KEY")' }
        @{ Line = 'Set the 51D_ cookie for the 51Did example.' }
    ) {
        Get-Kind $Line | Should -BeNullOrEmpty
    }

    # The packages still read the old cloud endpoint name when the new one
    # is not set, and keep the constants that hold it, so the check does not
    # fail on it.
    It 'leaves the old cloud endpoint name alone in <Line>' -ForEach @(
        @{ Line = 'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';' }
        @{ Line = 'public const FOD_CLOUD_API_URL = ''FOD_CLOUD_API_URL'';' }
        @{ Line = 'getenv(Constants::FOD_CLOUD_API_URL)' }
        @{ Line = 'getenv(''fod_cloud_api_url'')' }
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
