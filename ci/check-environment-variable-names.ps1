# Every 51Degrees environment variable name in this repository starts with an
# underscore, because a POSIX shell cannot set a name that starts with a digit.
# The names the variables had before are still read, as deprecated names, when
# the variable is not set, and only by the files listed below, which are the
# code that reads them and the tests of that code. This reads every file git
# tracks, which leaves out build output and the submodules, and fails on a
# name that starts with the digit, or an old name, in any other file.
#
# Dot-sourcing the script defines Find-EnvironmentNameProblem without reading
# the repository, which is how its tests use it.

[CmdletBinding()]
param(
    # The root of the checkout to read. The default is the repository this
    # script lives in.
    [string]$RepoRoot = (Join-Path $PSScriptRoot '..')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# The start of a name a shell cannot set, split so this file does not match
# itself.
$digitInitial = '5' + '1DEGREES_'

# A name is not matched when a name character comes before it, so a name with
# a leading underscore is left alone. It is matched in any case, because
# Windows reads an environment variable name in any case, so a lower case
# name that starts with the digit is still one a shell cannot set.
$script:DigitInitialPattern = [regex]::new(
    "(?<![A-Za-z0-9_])$digitInitial[A-Za-z0-9_]*",
    [Text.RegularExpressions.RegexOptions]::IgnoreCase)

# An old name read or set as an environment variable, or held in a constant
# to read later. Only a read, a set or such a constant is matched, so
# placeholder text such as a key written between exclamation marks, and longer
# names that end in the same words, are left alone.
#
# These are matched in any case, as the digit-initial names are, because
# Windows reads an environment variable name in any case. The pattern kept its
# case while the IP Intelligence and location examples and tests read
# resource_key, which differs from RESOURCE_KEY only by case. That name has
# gone, so the reason has gone with it.
#
# Two kinds of old name. The old IP Intelligence data file name, the three
# FIFTYONE_ names the fork safety test read and the old cloud endpoint name
# are never anything but an environment variable, so they are matched
# wherever they appear. Each is built from two pieces below so that this file
# does not name one itself. RESOURCE_KEY, LICENSE_KEY and CLOUD_ENDPOINT also
# name things that are not environment variables, being the resource_key
# query string parameter the cloud service takes and the cloud_endpoint
# pipeline setting, so those three are matched only where the line reads or
# sets a variable, names one in a constant whose own name says so, or gives
# one as the deprecated name to a helper that reads a variable by its name
# and then by the name it replaced.
#
# The env token is matched on its own, with a following backslash allowed,
# rather than next to a dollar sign, because PowerShell braces a name as well
# as prefixing it and reads one through the Env: drive, and a workflow
# expression braces one too. Wanting the dollar beside the token misses every
# braced form, which the CI scripts here write.
$plainOld = '(?:IPINTELLIGENCE' + 'DATAFILE|FIFTYONE_DATA' + '_FILE|' +
    'FIFTYONE_UA' + '_CSV|FIFTYONE_WRAP' + 'PER|FOD_CLOUD' + '_API_URL)'
$sharedOld = '(?:RESOURCE_KEY|LICENSE_KEY|CLOUD_ENDPOINT)'
$script:FallbackPattern = [regex]::new(
    "(?<![A-Za-z0-9_])$plainOld(?![A-Za-z0-9_])|" +
    '(?:getenv\(\s*[''"]|\$_(?:ENV|SERVER)\[\s*[''"]|env[:.]\\?|' +
    '<env\s+name="|SetEnvironmentVariable\(\s*[''"]|putenv\(\s*[''"]|' +
    '(?:readVariable|::read)\(\s*[''"][A-Za-z0-9_]+[''"],\s*[''"]|' +
    "[A-Za-z_][A-Za-z0-9_]*(?:ENV_VAR|ENVIRONMENT_VARIABLE|_VARIABLE)" +
    "[A-Za-z0-9_]*\s*=\s*['`"])$sharedOld(?![A-Za-z0-9_])|" +
    '\bLEGACY_[A-Z_]*ENV_VARS?\b',
    [Text.RegularExpressions.RegexOptions]::IgnoreCase)

# The problems on one line, each as its kind, 'digit' or 'fallback', and the
# text matched.
function Find-EnvironmentNameProblem([string]$Line) {
    foreach ($match in $script:DigitInitialPattern.Matches($Line)) {
        [pscustomobject]@{ Kind = 'digit'; Value = $match.Value }
    }
    foreach ($match in $script:FallbackPattern.Matches($Line)) {
        [pscustomobject]@{ Kind = 'fallback'; Value = $match.Value }
    }
}

# The files that may name an old variable, which are the code that reads one
# as a deprecated name when the variable is not set, the constants that hold
# one, and the tests of both. Paths are relative to the repository root.
$script:DeprecatedNameFiles = @(
    'device-detection/cloud/examples/cloud/classes/ExampleUtils.php'
    'device-detection/cloud/tests/EnvironmentVariableTests.php'
    'device-detection/cloud/tests/classes/Constants.php'
    'device-detection/cloud/tests/classes/ResourceKeys.php'
    'device-detection/onpremise/configuration-tests/testForkSafety.php'
    'device-detection/onpremise/examples/onpremise/classes/ExampleUtils.php'
    'device-detection/onpremise/tests/EnvironmentVariableTests.php'
    'ip-intelligence/examples/classes/ExampleConstants.php'
    'ip-intelligence/src/translation/TranslationPaths.php'
    'ip-intelligence/tests/EnvironmentVariableTests.php'
    'ip-intelligence/tests/IpIntelligenceCloudTests.php'
    'ip-intelligence/tests/classes/Constants.php'
    'location/examples/cloud/classes/Constants.php'
    'location/tests/EnvironmentVariableTests.php'
    'pipeline/cloudrequestengine/src/CloudRequestEngine.php'
    'pipeline/cloudrequestengine/src/Constants.php'
    'pipeline/cloudrequestengine/tests/CloudRequestEngineTests.php'
    'pipeline/cloudrequestengine/tests/CloudRequestEngineTestsBase.php'
    'pipeline/cloudrequestengine/tests/EnvironmentVariableTests.php'
    'pipeline/did/examples/creator-context-web/server.php'
    'pipeline/did/src/DidClient.php'
    'pipeline/did/tests/DidClientLiveTest.php'
    'pipeline/did/tests/DidClientTest.php'
    'pipeline/did/tests/EnvironmentVariableTest.php'
)

# The one name starting with the digit that is still read, which is the old
# name of the Device Detection data file variable.
$script:DeprecatedDigitName = $digitInitial + 'DD_PATH'

# True when a problem found in a file is a deprecated name in one of the files
# that may name one. An old name is allowed in any of them. A name starting
# with the digit is allowed only when it is the one that is still read.
function Test-DeprecatedNameAllowed([string]$Path, $Problem) {
    if ($script:DeprecatedNameFiles -cnotcontains $Path.Replace('\', '/')) {
        return $false
    }
    return $Problem.Kind -eq 'fallback' -or
        $Problem.Value -ceq $script:DeprecatedDigitName
}

if ($MyInvocation.InvocationName -eq '.') {
    return
}

$root = (Resolve-Path $RepoRoot).Path
# Paths are listed as they are, so a path holding a non-ASCII character is not
# quoted and is still found.
$tracked = & git -C $root -c core.quotePath=false ls-files
if ($LASTEXITCODE -ne 0) {
    throw "git ls-files failed in $root"
}

# The tests of this script name every form it fails on, on purpose.
$ownTests = 'ci/check-environment-variable-names.Tests.ps1'

$found = @()
$fallbacks = @()
$allowed = 0
$read = 0
$skipped = @()
foreach ($relative in $tracked) {
    if ($relative -eq $ownTests) {
        continue
    }
    $path = Join-Path $root $relative
    # A submodule is tracked as a directory, and its files belong to another
    # repository.
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        $skipped += $relative
        continue
    }
    $read++
    $lineNumber = 0
    foreach ($line in [IO.File]::ReadLines($path)) {
        $lineNumber++
        foreach ($problem in Find-EnvironmentNameProblem $line) {
            if (Test-DeprecatedNameAllowed $relative $problem) {
                $allowed++
                continue
            }
            $text = "${relative}:${lineNumber}: $($problem.Value)"
            if ($problem.Kind -eq 'digit') {
                $found += $text
            } else {
                $fallbacks += $text
            }
        }
    }
}

Write-Output ("$read paths read, $($skipped.Count) skipped as not files: " +
    "$($skipped -join ', '), and $ownTests left out")
Write-Output ("$allowed deprecated names left alone in the " +
    "$($script:DeprecatedNameFiles.Count) files that may name one")

if ($found.Count -gt 0 -or $fallbacks.Count -gt 0) {
    Write-Output $found
    Write-Output $fallbacks
    throw ("$($found.Count) names start with a digit, which a POSIX shell " +
        'cannot set, so each must start with an underscore, and ' +
        "$($fallbacks.Count) old names are read, set or kept outside the " +
        'files that read them as deprecated names.')
}

Write-Output ('No tracked file names a variable starting with a digit or an ' +
    'old name, outside the files that read the old names as deprecated names.')
