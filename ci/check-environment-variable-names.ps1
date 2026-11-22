# Every environment variable this repository reads or sets, and every secret
# and organization variable its workflows read, has a name that starts with
# _51DEGREES_. The only other names are the ones the operating system, the
# runner, PHP or another tool defines, which are listed below. A name that
# starts with a digit, as the 51Degrees names once did, is refused wherever it
# appears, because a POSIX shell cannot set it.
#
# This reads every file git tracks, which leaves out build output and the
# submodules. Dot-sourcing the script defines Find-EnvironmentNameProblem
# without reading the repository, which is how its tests use it.

[CmdletBinding()]
param(
    # The root of the checkout to read. The default is the repository this
    # script lives in.
    [string]$RepoRoot = (Join-Path $PSScriptRoot '..')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# The prefix every name this repository defines starts with.
$script:Prefix = '_51DEGREES_'

# The start of a name a shell cannot set, split so this file does not match
# itself. It is matched in any case, because Windows reads an environment
# variable name in any case, and not after a name character, so a name with
# the leading underscore is left alone.
$digitInitial = '5' + '1DEGREES_'
$script:DigitInitialPattern = [regex]::new(
    "(?<![A-Za-z0-9_])$digitInitial[A-Za-z0-9_]*",
    [Text.RegularExpressions.RegexOptions]::IgnoreCase)

# Names that the operating system, the runner, PHP, Composer, the browsers or
# the web server define, which keep the names those tools give them.
$script:PlatformNames = @(
    'PATH', 'HOME', 'TEMP', 'TMP', 'TMPDIR', 'CI',
    'COMPOSER', 'PHP_INI_SCAN_DIR', 'USE_ZEND_ALLOC',
    'ZEND_DONT_UNLOAD_MODULES', 'GIT_INDEX_FILE',
    'CHROMEWEBDRIVER', 'GECKOWEBDRIVER', 'CHROME_BIN', 'FIREFOX_BIN',
    'DEBIAN_FRONTEND',
    # setup-php's setting for a thread safe PHP
    'phpts'
)

# Prefixes of names that a platform defines, being the runner's own, the web
# request values PHP puts in $_SERVER, and the C compiler's flags.
$script:PlatformPrefixes = @(
    'GITHUB_', 'RUNNER_', 'HTTP_', 'REQUEST_', 'SCRIPT_', 'SERVER_',
    'REMOTE_', 'CONTENT_', 'DOCUMENT_', 'PHP_SELF', 'QUERY_STRING',
    'PATH_INFO', 'HTTPS'
)

# The names the shared browser suite, selenium-api-tests, reads, which the
# browser runs here set for it. They change when that suite reads
# _51DEGREES_ names.
$script:BrowserSuiteNames = @('EXAMPLE_URL', 'EXAMPLE_LANG')

# Each way a name is read or set, with the name in the group 'name'. A name
# given to a helper, held in a constant named for a variable, or set in
# phpunit.xml counts as well as a direct read, because each is read later.
$name = '(?<name>[A-Za-z_][A-Za-z0-9_]*)'
$quote = '[''"]'
$script:ReadPatterns = @(
    # PHP
    "getenv\(\s*$quote$name"
    "putenv\(\s*$quote$name"
    "\`$_ENV\[\s*$quote$name"
    "\`$_SERVER\[\s*$quote$name"
    "EnvironmentVariable::read\(\s*$quote$name"
    "\bconst\s+[A-Za-z_]*(?:ENV_VAR|VARIABLE)S?\s*=\s*\[?\s*$quote$name"
    "<(?:env|server)\s+name=`"$name"
    # PowerShell, with or without braces, and through the Env: drive
    "\`$\{?env:\\?$name"
    "\bEnv:\\$name"
    "(?:Get|Set)EnvironmentVariable\(\s*$quote$name"
    # Workflow expressions
    "\`$\{\{\s*(?:env|secrets|vars)\.$name"
    # Shell
    "\bexport\s+$name="
    "\s-e\s+$name="
) | ForEach-Object {
    [regex]::new($_, [Text.RegularExpressions.RegexOptions]::IgnoreCase)
}

# True when a name may be read or set here.
function Test-NameAllowed([string]$Name) {
    if ($Name.StartsWith($script:Prefix, [StringComparison]::Ordinal)) {
        return $true
    }
    if ($script:PlatformNames -ccontains $Name -or
        $script:BrowserSuiteNames -ccontains $Name) {
        return $true
    }
    foreach ($prefix in $script:PlatformPrefixes) {
        if ($Name.StartsWith($prefix, [StringComparison]::Ordinal)) {
            return $true
        }
    }
    return $false
}

# The problems on one line, each as its kind, 'digit' or 'name', and the text
# matched.
function Find-EnvironmentNameProblem([string]$Line) {
    foreach ($match in $script:DigitInitialPattern.Matches($Line)) {
        [pscustomobject]@{ Kind = 'digit'; Value = $match.Value }
    }
    foreach ($pattern in $script:ReadPatterns) {
        foreach ($match in $pattern.Matches($Line)) {
            $found = $match.Groups['name'].Value
            # A name starting with the digit is reported once, above.
            if (-not (Test-NameAllowed $found)) {
                [pscustomobject]@{ Kind = 'name'; Value = $found }
            }
        }
    }
}

# The names a workflow file sets in its env: blocks, each with its line
# number. A key of an env: block is a variable name, whatever sets its value.
function Find-WorkflowEnvironmentName([string[]]$Lines) {
    $indent = -1
    for ($i = 0; $i -lt $Lines.Count; $i++) {
        $line = $Lines[$i]
        if ($line -match '^(\s*(?:-\s+)?)env:[ \t]*(#.*)?$') {
            $indent = $Matches[1].Length
            continue
        }
        if ($indent -lt 0 -or $line -match '^\s*(#.*)?$') {
            continue
        }
        if ($line -match '^(\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:' -and
            $Matches[1].Length -gt $indent) {
            [pscustomobject]@{ Line = $i + 1; Name = $Matches[2] }
        } elseif (($line -match '^(\s*)\S') -and $Matches[1].Length -le $indent) {
            $indent = -1
        }
    }
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
    $lines = [IO.File]::ReadAllLines($path)
    for ($i = 0; $i -lt $lines.Count; $i++) {
        foreach ($problem in Find-EnvironmentNameProblem $lines[$i]) {
            $found += "${relative}:$($i + 1): $($problem.Value)"
        }
    }
    if ($relative -like '.github/workflows/*.yml') {
        foreach ($set in Find-WorkflowEnvironmentName $lines) {
            if (-not (Test-NameAllowed $set.Name)) {
                $found += "${relative}:$($set.Line): $($set.Name)"
            }
        }
    }
}

Write-Output ("$read paths read, $($skipped.Count) skipped as not files: " +
    "$($skipped -join ', '), and $ownTests left out")

if ($found.Count -gt 0) {
    Write-Output ($found | Sort-Object -Unique)
    throw ("$($found.Count) names are read or set that do not start with " +
        "$($script:Prefix) and are not a platform's own, or start with " +
        'the digit, which a POSIX shell cannot set.')
}

Write-Output ("Every name read or set starts with $($script:Prefix), or is " +
    'a platform name, and none starts with the digit.')
