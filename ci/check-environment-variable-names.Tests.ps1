# Tests for ci/check-environment-variable-names.ps1.
#
# Each case is one line of text and what the check must make of it. The
# names are matched in any case, because Windows reads an environment
# variable name in any case, so a lower case old name is still the old name
# there. The old names are still read as deprecated names, so the check
# leaves them alone in the files that read them and fails on them anywhere
# else.

BeforeAll {
    $script:Check = Join-Path $PSScriptRoot 'check-environment-variable-names.ps1'
    . $script:Check

    function Get-Kind([string]$Line) {
        @(Find-EnvironmentNameProblem $Line | ForEach-Object { $_.Kind })
    }

    # Whether every problem on the line is allowed in the file.
    function Test-LineAllowed([string]$Path, [string]$Line) {
        $problems = @(Find-EnvironmentNameProblem $Line)
        $problems.Count -gt 0 -and @($problems | Where-Object {
            -not (Test-DeprecatedNameAllowed $Path $_)
        }).Count -eq 0
    }

    # Runs the check over a new repository holding one file with one line.
    function Invoke-CheckOver([string]$Path, [string]$Line) {
        $repo = Join-Path ([IO.Path]::GetTempPath()) ([Guid]::NewGuid())
        New-Item -ItemType Directory $repo | Out-Null
        try {
            git -C $repo init -q
            $file = Join-Path $repo $Path
            New-Item -ItemType Directory (Split-Path $file) -Force | Out-Null
            Set-Content -LiteralPath $file -Value $Line
            git -C $repo add -A
            & $script:Check -RepoRoot $repo
        } finally {
            Remove-Item -Recurse -Force $repo
        }
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
        @{ Line = 'getenv(''FIFTYONE_WRAPPER'')' }
        @{ Line = 'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';' }
        @{ Line = 'public const FOD_CLOUD_API_URL = ''FOD_CLOUD_API_URL'';' }
        @{ Line = 'getenv(Constants::FOD_CLOUD_API_URL)' }
        @{ Line = 'getenv(''fod_cloud_api_url'')' }
        @{ Line = '$key = readVariable(''_51DEGREES_RESOURCE_KEY'', ''RESOURCE_KEY'');' }
        @{ Line = 'EnvironmentVariable::read(''_51DEGREES_LICENSE_KEY'', ''LICENSE_KEY'')' }
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

}

# The old names are still read as deprecated names, by the files the check
# lists, so they are allowed there and nowhere else.
Describe 'Test-DeprecatedNameAllowed' {

    It 'allows <Line> in <Path>' -ForEach @(
        @{ Path = 'pipeline/cloudrequestengine/src/Constants.php'
           Line = 'public const FOD_CLOUD_API_URL = ''FOD_CLOUD_API_URL'';' }
        @{ Path = 'pipeline/did/src/DidClient.php'
           Line = 'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';' }
        @{ Path = 'pipeline\did\src\DidClient.php'
           Line = 'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';' }
        @{ Path = 'pipeline/did/examples/creator-context-web/server.php'
           Line = '$key = readVariable(''_51DEGREES_RESOURCE_KEY'', ''RESOURCE_KEY'');' }
        @{ Path = 'device-detection/cloud/examples/cloud/classes/ExampleUtils.php'
           Line = 'public const LEGACY_RESOURCE_KEY_ENV_VAR = ''resource_key'';' }
        @{ Path = 'device-detection/cloud/examples/cloud/classes/ExampleUtils.php'
           Line = 'public const ENDPOINT_ENV_VAR = ''cloud_endpoint'';' }
        @{ Path = 'device-detection/cloud/tests/classes/ResourceKeys.php'
           Line = 'Constants::LEGACY_ENV_VARS[$name] ?? $name,' }
        @{ Path = 'device-detection/onpremise/examples/onpremise/classes/ExampleUtils.php'
           Line = 'public const DATA_FILE_ENV_VAR = ''51DEGREES_DD_PATH'';' }
        @{ Path = 'device-detection/onpremise/configuration-tests/testForkSafety.php'
           Line = 'readVariable(''_51DEGREES_DD_PATH'', ''FIFTYONE_DATA_FILE'')' }
        @{ Path = 'ip-intelligence/src/translation/TranslationPaths.php'
           Line = 'private const OLD_IPI_PATH_VARIABLE = ''IPINTELLIGENCEDATAFILE'';' }
        @{ Path = 'location/examples/cloud/classes/Constants.php'
           Line = 'const RESOURCE_KEY_ENV_VAR = ''resource_key'';' }
    ) {
        Test-LineAllowed $Path $Line | Should -BeTrue
    }

    It 'does not allow <Line> in <Path>' -ForEach @(
        @{ Path = 'pipeline/core/src/Pipeline.php'
           Line = 'getenv(''FOD_CLOUD_API_URL'')' }
        @{ Path = 'location/examples/cloud/gettingstarted.php'
           Line = 'getenv(''resource_key'')' }
        @{ Path = 'ip-intelligence/examples/classes/ExampleUtils.php'
           Line = 'getenv(''IPINTELLIGENCEDATAFILE'')' }
        @{ Path = 'device-detection/onpremise/ci/run-integration-tests.ps1'
           Line = '[Environment]::SetEnvironmentVariable(''51DEGREES_DD_PATH'', $dataFile)' }
        @{ Path = 'Pipeline/Did/Src/DidClient.php'
           Line = 'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';' }
        # A name starting with the digit is allowed only when it is the one
        # that is still read.
        @{ Path = 'device-detection/onpremise/examples/onpremise/classes/ExampleUtils.php'
           Line = 'public const IPI_FILE_ENV_VAR = ''51DEGREES_IPI_PATH'';' }
        @{ Path = 'device-detection/onpremise/examples/onpremise/classes/ExampleUtils.php'
           Line = 'getenv(''51degrees_dd_path'')' }
    ) {
        Test-LineAllowed $Path $Line | Should -BeFalse
    }

    It 'lists only files that are in the repository' {
        $root = Join-Path $PSScriptRoot '..'
        $script:DeprecatedNameFiles |
            Where-Object { -not (Test-Path -LiteralPath (Join-Path $root $_) -PathType Leaf) } |
            Should -BeNullOrEmpty
    }
}

Describe 'the check over a repository' {

    It 'passes on an old name in a file that reads it as a deprecated name' {
        $output = Invoke-CheckOver 'pipeline/did/src/DidClient.php' `
            'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';'
        $output | Should -Contain ('1 deprecated names left alone in the ' +
            "$($script:DeprecatedNameFiles.Count) files that may name one")
    }

    It 'fails on the same old name in any other file' {
        { Invoke-CheckOver 'pipeline/core/src/Pipeline.php' `
            'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';' } |
            Should -Throw '*1 old names are read, set or kept outside*'
    }

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
