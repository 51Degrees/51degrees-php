# Every 51Degrees environment variable name in this repository starts with an
# underscore, because a POSIX shell cannot set a name that starts with a digit,
# and each variable has one name with nothing read in its place. This reads
# every file git tracks, which leaves out build output and the submodules, and
# fails on any name that starts with the digit and on any fallback name.
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
# a leading underscore is left alone.
$script:DigitInitialPattern = [regex]::new(
    "(?<![A-Za-z0-9_])$digitInitial[A-Za-z0-9_]*")

# A bare RESOURCE_KEY or LICENSE_KEY read or set as an environment variable,
# or a constant holding a legacy name to fall back to. Only a read, a set or
# such a constant is matched, so placeholder text such as a key written
# between exclamation marks, and longer names that end in the same words, are
# left alone.
$script:FallbackPattern = [regex]::new(
    '(?:getenv\(\s*[''"]|\$_(?:ENV|SERVER)\[\s*[''"]|\$env:|' +
    '<env\s+name="|SetEnvironmentVariable\(\s*[''"])' +
    '(?:RESOURCE_KEY|LICENSE_KEY)(?![A-Za-z0-9_])|' +
    '\bLEGACY_[A-Z_]*ENV_VARS?\b')

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

if ($MyInvocation.InvocationName -eq '.') {
    return
}

$root = (Resolve-Path $RepoRoot).Path
$tracked = & git -C $root ls-files
if ($LASTEXITCODE -ne 0) {
    throw "git ls-files failed in $root"
}

# The tests of this script name every form it fails on, on purpose.
$ownTests = 'ci/check-environment-variable-names.Tests.ps1'

$found = @()
$fallbacks = @()
foreach ($relative in $tracked) {
    if ($relative -eq $ownTests) {
        continue
    }
    $path = Join-Path $root $relative
    # A submodule is tracked as a directory, and its files belong to another
    # repository.
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        continue
    }
    $lineNumber = 0
    foreach ($line in [IO.File]::ReadLines($path)) {
        $lineNumber++
        foreach ($problem in Find-EnvironmentNameProblem $line) {
            $text = "${relative}:${lineNumber}: $($problem.Value)"
            if ($problem.Kind -eq 'digit') {
                $found += $text
            } else {
                $fallbacks += $text
            }
        }
    }
}

if ($found.Count -gt 0 -or $fallbacks.Count -gt 0) {
    Write-Output $found
    Write-Output $fallbacks
    throw ("$($found.Count) names start with a digit, which a POSIX shell " +
        'cannot set, so each must start with an underscore, and ' +
        "$($fallbacks.Count) fallback names are read, set or kept, where " +
        'each variable has one name.')
}

Write-Output "No tracked file names a variable starting with a digit or a fallback name. $($tracked.Count) paths read."
