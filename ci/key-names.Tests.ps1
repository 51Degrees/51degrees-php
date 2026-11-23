# Tests that the CI scripts read their keys only under the _51DEGREES_ names.
#
# The workflows pass their secrets to common-ci with 'secrets: inherit', and
# common-ci puts each secret and organization variable into the environment
# under its own name before any script of this repository runs. So no script
# here takes a key as a parameter, and a workflow that sets a key gives it the
# organization variable (a resource key) or secret (a license key) of the same
# name.
#
# Each package script is copied into the test drive at its path in the
# repository, and run there with a stand-in for the shared step it calls, so
# that nothing in the checkout is touched.

BeforeDiscovery {
    # The scripts that fetch the data files.
    $FetchScripts = @(
        'ci/fetch-assets.ps1'
        'device-detection/cloud/ci/fetch-assets.ps1'
        'device-detection/onpremise/ci/fetch-assets.ps1'
        'ip-intelligence/ci/fetch-assets.ps1'
    )
}

BeforeAll {
    $script:Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

    # Copies one script into a new folder at its path in the repository,
    # with a stand-in for the shared step at steps/fetch-assets.ps1 and for
    # php/run-integration-tests.ps1 in the folder it runs from. The stand-ins
    # write what they were called with to calls.json and create each asset
    # asked for. Returns the folder it runs from.
    function New-Sandbox([string]$Script) {
        $sandbox = Join-Path $TestDrive ([Guid]::NewGuid())
        $copy = Join-Path $sandbox 'repo' $Script
        New-Item -ItemType Directory (Split-Path $copy) -Force | Out-Null
        Copy-Item -LiteralPath (Join-Path $script:Root $Script) $copy
        $work = New-Item -ItemType Directory (Join-Path $sandbox 'work')
        New-Item -ItemType Directory (Join-Path $work 'steps'),
            (Join-Path $work 'php') | Out-Null
        Set-Content -LiteralPath (Join-Path $work 'steps' 'fetch-assets.ps1') -Value @'
param([Parameter(ValueFromRemainingArguments)]$Rest)
$call = @{ Script = 'fetch-assets'; Arguments = @($Rest | ForEach-Object { $_ } | ForEach-Object { "$_" }) }
$call | ConvertTo-Json | Set-Content calls.json
New-Item -ItemType Directory assets -Force | Out-Null
'@
        Set-Content -LiteralPath (Join-Path $work 'php' 'run-integration-tests.ps1') -Value @'
param([string]$RepoName)
$names = @(Get-ChildItem Env: | Where-Object Name -like '_51DEGREES_*' |
    ForEach-Object Name)
@{ Script = 'php'; Names = $names } | ConvertTo-Json | Set-Content calls.json
$global:LASTEXITCODE = 0
'@
        return @{ Work = $work.FullName; Copy = $copy }
    }

    # Runs a copied script from its sandbox and returns its output and what
    # the stand-in it called was given. A failure after that call, such as a
    # symbolic link the runner may not make, is kept as output rather than
    # thrown, because what is tested is what the script asked for.
    function Invoke-Sandboxed($Sandbox, [hashtable]$Parameters = @{}) {
        Push-Location $Sandbox.Work
        try {
            # Only the parameters the script takes are passed, as common-ci
            # passes its options.
            $accepted = (Get-Command $Sandbox.Copy).Parameters
            $Parameters = $Parameters.Clone()
            @($Parameters.PSBase.Keys) | Where-Object { !$accepted.ContainsKey($_) } |
                ForEach-Object { $Parameters.Remove($_) }
            $lines = [Collections.Generic.List[string]]::new()
            try {
                & $Sandbox.Copy @Parameters *>&1 | ForEach-Object { $lines.Add("$_") }
            } catch {
                $lines.Add("Failed: $_")
            }
            $output = $lines -join "`n"
            $calls = Join-Path $Sandbox.Work 'calls.json'
            $call = if (Test-Path $calls) {
                Get-Content -Raw $calls | ConvertFrom-Json
            }
            return @{ Output = $output; Call = $call }
        } finally {
            Pop-Location
        }
    }

    # Saves and clears every _51DEGREES_ variable, so that each test sets
    # only the ones it needs.
    function Save-KeyVariables {
        $script:Saved = @(Get-ChildItem Env: | Where-Object Name -like '_51DEGREES_*')
        $script:Saved | ForEach-Object { Remove-Item -LiteralPath "Env:$($_.Name)" }
    }

    function Restore-KeyVariables {
        Get-ChildItem Env: | Where-Object Name -like '_51DEGREES_*' |
            ForEach-Object { Remove-Item -LiteralPath "Env:$($_.Name)" }
        $script:Saved | ForEach-Object {
            Set-Item -LiteralPath "Env:$($_.Name)" -Value $_.Value
        }
    }
}

Describe 'Fetching the data files' {
    BeforeEach { Save-KeyVariables; $env:GITHUB_JOB = 'Test' }
    AfterEach { Restore-KeyVariables; Remove-Item Env:GITHUB_JOB -ErrorAction Ignore }

    # Only PowerShell's own parameters, so no key reaches a script as one.
    It '<_> takes no key or address parameter' -ForEach $FetchScripts {
        $common = [Management.Automation.PSCmdlet]::CommonParameters +
            [Management.Automation.PSCmdlet]::OptionalCommonParameters
        @((Get-Command (Join-Path $script:Root $_)).Parameters.Keys |
            Where-Object { $_ -notin $common }) | Should -BeNullOrEmpty
    }

    It '<Script> asks for <Asset> when _51DEGREES_LICENSE_KEY is set' -ForEach @(
        @{ Script = 'device-detection/cloud/ci/fetch-assets.ps1'; Asset = '51Degrees.csv' }
        @{ Script = 'device-detection/onpremise/ci/fetch-assets.ps1'; Asset = 'TAC-HashV41.hash' }
        @{ Script = 'ip-intelligence/ci/fetch-assets.ps1'; Asset = '51Degrees-EnterpriseIpiV41.ipi' }
    ) {
        $env:_51DEGREES_LICENSE_KEY = 'license-for-the-test'
        $result = Invoke-Sandboxed (New-Sandbox $Script)
        $result.Call.Arguments | Should -Contain $Asset
        # The key reaches the shared step through the environment, never
        # as an argument.
        $result.Call.Arguments | Should -Not -Contain 'license-for-the-test'
    }

    It '<Script> names _51DEGREES_LICENSE_KEY and leaves out <Asset> without it' -ForEach @(
        @{ Script = 'device-detection/cloud/ci/fetch-assets.ps1'; Asset = '51Degrees.csv' }
        @{ Script = 'device-detection/onpremise/ci/fetch-assets.ps1'; Asset = 'TAC-HashV41.hash' }
        @{ Script = 'ip-intelligence/ci/fetch-assets.ps1'; Asset = '51Degrees-EnterpriseIpiV41.ipi' }
    ) {
        $result = Invoke-Sandboxed (New-Sandbox $Script)
        $result.Output | Should -Match '_51DEGREES_LICENSE_KEY'
        @($result.Call.Arguments) | Should -Not -Contain $Asset
    }
}

Describe 'Running the integration tests' {
    BeforeEach { Save-KeyVariables }
    AfterEach { Restore-KeyVariables }

    # Each package whose integration tests need a resource key.
    It '<_> runs them with _51DEGREES_RESOURCE_KEY_BESPOKE' -ForEach @(
        'device-detection/cloud/ci/run-integration-tests.ps1'
        'location/ci/run-integration-tests.ps1'
        'pipeline/cloudrequestengine/ci/run-integration-tests.ps1'
    ) {
        $env:_51DEGREES_RESOURCE_KEY_BESPOKE = 'resource-key-for-the-test'
        $sandbox = New-Sandbox $_
        # The device detection tests also need the CSV file.
        New-Item -ItemType Directory (Join-Path $sandbox.Work 'pkg' 'tests') -Force | Out-Null
        Set-Content (Join-Path $sandbox.Work 'pkg' 'tests' '51Degrees.csv') 'csv'
        $result = Invoke-Sandboxed $sandbox @{
            RepoName = 'pkg'; Keys = @{}; SeleniumContract = $false
        }
        $result.Call.Script | Should -Be 'php' -Because $result.Output
        $result.Call.Names | Should -Contain '_51DEGREES_RESOURCE_KEY_BESPOKE'
    }

    It '<_> names _51DEGREES_RESOURCE_KEY_BESPOKE and skips without it' -ForEach @(
        'device-detection/cloud/ci/run-integration-tests.ps1'
        'location/ci/run-integration-tests.ps1'
        'pipeline/cloudrequestengine/ci/run-integration-tests.ps1'
    ) {
        $sandbox = New-Sandbox $_
        $result = Invoke-Sandboxed $sandbox @{
            RepoName = 'pkg'; SeleniumContract = $false
        }
        $result.Output | Should -Match '_51DEGREES_RESOURCE_KEY_BESPOKE'
        $result.Call | Should -BeNullOrEmpty
    }
}

Describe 'The keys the workflows set' {
    # Each resource key is filled from the organization variable of exactly
    # its name, and each license key from the organization secret of exactly
    # its name, so no workflow copies one key into another name.
    It 'are each given the variable or secret of the same name' {
        $lines = @(Get-ChildItem (Join-Path $script:Root '.github/workflows') -Filter *.yml |
            Select-String -Pattern '^\s*(_51DEGREES_(?:RESOURCE|LICENSE)_KEY\w*)\s*:' |
            ForEach-Object {
                $name = $_.Matches[0].Groups[1].Value
                $store = if ($name -like '_51DEGREES_RESOURCE_KEY*') { 'vars' } else { 'secrets' }
                if ($_.Line -notmatch (':\s*\$\{\{\s*' + $store + '\.' + $name + '\s*\}\}\s*$')) {
                    "$($_.Filename):$($_.LineNumber): $($_.Line.Trim())"
                }
            })
        $lines | Should -BeNullOrEmpty
    }
}
