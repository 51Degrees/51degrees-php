# Every 51Degrees environment variable name in this repository starts with an
# underscore, because a POSIX shell cannot set a name that starts with a digit,
# and each variable has one name with nothing read in its place. This reads
# every file git tracks, which leaves out build output and the submodules, and
# fails on any name that starts with the digit and on any fallback name.

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
$pattern = "(?<![A-Za-z0-9_])$digitInitial[A-Za-z0-9_]*"

# A bare RESOURCE_KEY or LICENSE_KEY read or set as an environment variable,
# or a constant holding a legacy name to fall back to. Only a read, a set or
# such a constant is matched, so placeholder text such as a key written
# between exclamation marks, and longer names that end in the same words, are
# left alone.
$fallback = '(?:getenv\(\s*[''"]|\$_(?:ENV|SERVER)\[\s*[''"]|\$env:|' +
    '<env\s+name="|SetEnvironmentVariable\(\s*[''"])' +
    '(?:RESOURCE_KEY|LICENSE_KEY)(?![A-Za-z0-9_])|' +
    '\bLEGACY_[A-Z_]*ENV_VARS?\b'

$root = (Resolve-Path $RepoRoot).Path
$tracked = & git -C $root ls-files
if ($LASTEXITCODE -ne 0) {
    throw "git ls-files failed in $root"
}

$found = @()
$fallbacks = @()
foreach ($relative in $tracked) {
    $path = Join-Path $root $relative
    # A submodule is tracked as a directory, and its files belong to another
    # repository.
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        continue
    }
    $lineNumber = 0
    foreach ($line in [IO.File]::ReadLines($path)) {
        $lineNumber++
        foreach ($match in [regex]::Matches($line, $pattern)) {
            $found += "${relative}:${lineNumber}: $($match.Value)"
        }
        foreach ($match in [regex]::Matches($line, $fallback)) {
            $fallbacks += "${relative}:${lineNumber}: $($match.Value)"
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
