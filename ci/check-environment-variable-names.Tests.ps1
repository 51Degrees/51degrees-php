# Tests for ci/check-environment-variable-names.ps1.
#
# Each case is one line of text and what the check must make of it. Every name
# read or set must start with _51DEGREES_ unless a platform defines it, and a
# name starting with the digit is refused in any case, because Windows reads
# an environment variable name in any case.

BeforeAll {
    $script:Check = Join-Path $PSScriptRoot 'check-environment-variable-names.ps1'
    . $script:Check

    function Get-Kind([string]$Line, [switch]$Shell) {
        @(Find-EnvironmentNameProblem $Line -Shell:$Shell | ForEach-Object { $_.Kind })
    }

    # Runs the check over a new repository holding one file with the lines.
    function Invoke-CheckOver([string]$Path, [string[]]$Lines) {
        $repo = Join-Path ([IO.Path]::GetTempPath()) ([Guid]::NewGuid())
        New-Item -ItemType Directory $repo | Out-Null
        try {
            git -C $repo init -q
            $file = Join-Path $repo $Path
            New-Item -ItemType Directory (Split-Path $file) -Force | Out-Null
            Set-Content -LiteralPath $file -Value $Lines
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
        @{ Line = 'The 51DEGREES_RESOURCE_KEY variable' }
    ) {
        Get-Kind $Line | Should -Contain 'digit'
    }

    It 'finds the name that is not a _51DEGREES_ name in <Line>' -ForEach @(
        @{ Line = 'getenv(''RESOURCE_KEY'')' }
        @{ Line = 'putenv(''LICENSE_KEY=x'')' }
        @{ Line = '$_ENV["LICENSE_KEY"]' }
        @{ Line = '$_SERVER[''RESOURCE_KEY'']' }
        @{ Line = 'EnvironmentVariable::read(''resource_key'')' }
        @{ Line = 'public const RESOURCE_KEY_ENV_VAR = ''resource_key'';' }
        @{ Line = 'public const ENDPOINT_VARIABLE = ''FOD_CLOUD_API_URL'';' }
        @{ Line = 'public const RESOURCE_KEY_VARIABLES = [''RESOURCE_KEY''];' }
        @{ Line = '<env name="RESOURCE_KEY" value="x"/>' }
        @{ Line = "<env name='RESOURCE_KEY' value='x'/>" }
        @{ Line = '$env:PORT = 8096' }
        @{ Line = '${env:LICENSE_KEY} = $key' }
        @{ Line = 'Remove-Item Env:\LICENSE_KEY' }
        @{ Line = 'Remove-Item Env:LICENSE_KEY' }
        @{ Line = 'Test-Path Env:RESOURCE_KEY' }
        @{ Line = '[Environment]::SetEnvironmentVariable("LICENSE_KEY", $k)' }
        @{ Line = '          url: ${{ env.CLOUD_ENDPOINT }}' }
        @{ Line = '          key: ${{ secrets.MIRROR_PUSH_TOKEN }}' }
        @{ Line = '          key: ${{ secrets[''MIRROR_PUSH_TOKEN''] }}' }
        @{ Line = '          key: ${{ vars.CLOUD_ENDPOINT }}' }
    ) {
        Get-Kind $Line | Should -Contain 'name'
    }

    It 'finds the shell form <Line> in a shell script or a workflow' -ForEach @(
        @{ Line = 'export FIFTYONE_DATA_FILE=/data/file.hash' }
        @{ Line = 'RESOURCE_KEY=abc php x.php' }
        @{ Line = '        UA_CSV="${CSV}" \' }
        @{ Line = 'cd examples && RESOURCE_KEY="a b" php x.php' }
        @{ Line = '    -e CSV="/repo/file.csv" \' }
        @{ Line = 'docker run -e RESOURCE_KEY image' }
        @{ Line = 'docker run --env RESOURCE_KEY=x image' }
    ) {
        Get-Kind $Line -Shell | Should -Contain 'name'
        # Outside a shell script or a workflow the same line is text.
        Get-Kind $Line | Should -BeNullOrEmpty
    }

    It 'leaves the shell line <Line> alone' -ForEach @(
        @{ Line = 'top=$(git ls-remote --tags "$mirror")' }
        @{ Line = 'dd="-d extension=$PWD/module.so' }
        @{ Line = 'version="$_51DEGREES_INPUT_VERSION"' }
        @{ Line = 'split="$(git subtree split)" || {' }
        @{ Line = 'pattern=''^[0-9]+$''' }
        @{ Line = 'PHP_INI_SCAN_DIR=":$PWD" php examples/x.php' }
        @{ Line = '_51DEGREES_RESOURCE_KEY=key php cloud/gettingStartedConsole.php' }
        @{ Line = 'if [ "$found" != "$version" ]; then' }
        @{ Line = 'echo -e FOO=bar' }
        @{ Line = 'grep -E "$pattern" file' }
        @{ Line = 'https://51degrees.com/pricing?utm_source=github&utm_medium=comment&utm_campaign=51degrees-php&utm_content=check-environment-variable-names.tests.ps1&utm_term=shell-line more text' }
    ) {
        Get-Kind $Line -Shell | Should -BeNullOrEmpty
    }

    It 'leaves <Line> alone' -ForEach @(
        @{ Line = 'getenv("_51DEGREES_RESOURCE_KEY")' }
        @{ Line = '$env:_51DEGREES_DD_PATH = "file.hash"' }
        @{ Line = '${env:_51DEGREES_RESOURCE_KEY_BESPOKE}' }
        @{ Line = '          key: ${{ vars._51DEGREES_RESOURCE_KEY_BASIC }}' }
        @{ Line = '          key: ${{ secrets._51DEGREES_LICENSE_KEY_51DID }}' }
        @{ Line = '          url: ${{ vars._51DEGREES_CLOUD_ENDPOINT }}' }
        @{ Line = 'public const RESOURCE_KEY_QUERY_PARAM = ''resource_key'';' }
        @{ Line = '$params = [''resourceKey'' => ''resource_key''];' }
        @{ Line = '$settings["cloud_endpoint"] = $endpoint;' }
        @{ Line = '$env:PHP_INI_SCAN_DIR = "$sep$PWD"' }
        @{ Line = 'if ($env:GITHUB_JOB -eq "PreBuild") {' }
        @{ Line = '$_SERVER[''HTTP_USER_AGENT'']' }
        @{ Line = '$_SERVER["REMOTE_ADDR"] = "0.0.0.0";' }
        @{ Line = '          key: ${{ secrets.GITHUB_TOKEN }}' }
        @{ Line = '$env:EXAMPLE_URL = "http://localhost:$port"' }
        @{ Line = 'Set the 51D_ cookie for the 51Did example.' }
        # Windows reads a platform's name in any case.
        @{ Line = '$env:Path = "$dir;$env:Path"' }
        @{ Line = 'Get-ChildItem Env: | Where-Object Name -like ''_51DEGREES_*''' }
        # A constant whose name is not a constant's upper case is code.
        @{ Line = 'const someVariable = "abc";' }
    ) {
        Get-Kind $Line | Should -BeNullOrEmpty
    }
}

Describe 'Find-WorkflowEnvironmentName' {

    It 'finds the names an env: block sets, at any level' {
        $names = Find-WorkflowEnvironmentName @(
            'env:'
            '  TOP_LEVEL: 1'
            'jobs:'
            '  build:'
            '    steps:'
            '      - name: Step'
            '        env:'
            '          # A comment'
            '          STEP_NAME: ${{ inputs.version }}'
            ''
            '          _51DEGREES_VERSION: ${{ inputs.version }}'
            '        run: echo'
            '      - env:'
            '          LIST_ITEM_NAME: 1'
            '      - uses: shivammathur/setup-php@v2'
            '        with:'
            '          php-version: ''8.5'''
        ) | ForEach-Object Name
        $names | Should -Be @('TOP_LEVEL', 'STEP_NAME', '_51DEGREES_VERSION', 'LIST_ITEM_NAME')
    }
}

Describe 'the check over a repository' {

    It 'passes on a file that reads only _51DEGREES_ and platform names' {
        $output = Invoke-CheckOver 'pipeline/core/src/Reader.php' @(
            'getenv("_51DEGREES_CLOUD_ENDPOINT");'
            '$_SERVER["HTTP_USER_AGENT"];'
        )
        $output | Should -Contain ('Every name read or set starts with ' +
            '_51DEGREES_, or is a platform name, and none starts with the digit.')
    }

    It 'fails on a name that is not a _51DEGREES_ name' {
        { Invoke-CheckOver 'pipeline/core/src/Reader.php' 'getenv("RESOURCE_KEY");' } |
            Should -Throw '1 names are read or set*'
    }

    It 'fails on a name a workflow sets that is not a _51DEGREES_ name' {
        { Invoke-CheckOver '.github/workflows/build.yml' @(
            'jobs:'
            '  build:'
            '    steps:'
            '      - env:'
            '          VERSION: ${{ inputs.version }}'
            '        run: echo "$VERSION"'
        ) } | Should -Throw '1 names are read or set*'
    }

    It 'reads a shell script for the shell forms and a readme not' {
        { Invoke-CheckOver 'dev/run.sh' 'RESOURCE_KEY=x php a.php' } |
            Should -Throw '1 names are read or set*'
        { Invoke-CheckOver 'readme.md' 'RESOURCE_KEY=x php a.php' } |
            Should -Not -Throw
    }

    # git quotes a path holding a non-ASCII character unless told not to,
    # and a quoted path is not found on disk.
    It 'reads a file whose name is not ASCII' {
        $name = 'donn' + [char]0xE9 + 'es.php'
        { Invoke-CheckOver $name 'getenv("51DEGREES_DD_PATH");' } |
            Should -Throw '1 names are read or set*'
    }

    It 'finds nothing to refuse in this repository' {
        { & $script:Check } | Should -Not -Throw
    }
}
