param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

# This reproduction forks the worker process the way a PHP process manager does
# (Apache MPM prefork, php-fpm), which needs the POSIX pcntl extension. It runs
# on Linux and macOS only.
if ($IsWindows) {
    Write-Output "Skipping fork-safety test: pcntl/fork is not available on Windows."
    return
}

# Load the extension the same way the unit tests do.
$env:PHP_INI_SCAN_DIR = "$([IO.Path]::PathSeparator)$PWD/$RepoName"
$env:_51DEGREES_DD_PATH = "$PWD/assets/TAC-HashV41.hash"
$env:_51DEGREES_EVIDENCE_YAML = "$PWD/assets/20000 Evidence Records.yml"

Write-Output "Running fork-safety reproduction (issue #38)..."
php "$RepoName/configuration-tests/testForkSafety.php"
$code = $LASTEXITCODE

if ($code -eq 0) {
    Write-Output "PASS: streaming profiles share the file position across a fork, in-memory MaxPerformance is safe."
} elseif ($code -eq 2) {
    # Missing pcntl, extension, data file or evidence file. Skip rather
    # than fail, since this is an environment limitation rather than a defect.
    Write-Output "::warning::Skipping fork-safety test (pcntl, extension, data file or evidence file unavailable)."
} else {
    throw "fork-safety test failed (exit $code)"
}
