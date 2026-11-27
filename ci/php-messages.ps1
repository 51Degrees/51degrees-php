# Gives a PHP that a CI script starts outside PHPUnit the settings that
# tests/bootstrap.php gives each PHP the tests start. The PHP reports every
# level and logs each message to one file. Assert-NoPhpMessage prints that
# file and fails the step when it holds a warning, notice, deprecation or
# fatal error.

# The line PHP logs for each of those. Strict Standards is the E_STRICT
# level of PHP 7.4.
$PhpMessageFails = '^\[[^]]*\] PHP (Warning|Notice|Deprecated|' +
    'Strict Standards|Fatal error|Parse error|Recoverable fatal error):'

# Writes the ini file to a new folder, adds the folder to PHP_INI_SCAN_DIR,
# and returns it. Call it after PHP_INI_SCAN_DIR has the script's own
# folders, so that this file is read last and its settings win.
function Add-PhpMessageLog {
    $folder = Join-Path ([IO.Path]::GetTempPath()) ('php-messages-' +
        [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $folder | Out-Null
    # A backslash in a quoted ini value can start an escape, so the path
    # has forward slashes, which PHP takes on every platform.
    $folder = $folder -replace '\\', '/'
    @('error_reporting = -1', 'log_errors = On',
        "error_log = `"$folder/php.log`"") |
        Set-Content "$folder/php-messages.ini"
    $sep = [IO.Path]::PathSeparator
    # An empty entry makes PHP scan its own folder too, which it does when
    # PHP_INI_SCAN_DIR is not set.
    $env:PHP_INI_SCAN_DIR = if ($env:PHP_INI_SCAN_DIR) {
        "$($env:PHP_INI_SCAN_DIR)$sep$folder"
    } else {
        "$sep$folder"
    }
    return $folder
}

# Takes the folder out of PHP_INI_SCAN_DIR, prints what PHP logged, removes
# the folder, and throws when the log holds a line that fails the step.
function Assert-NoPhpMessage ([Parameter(Mandatory)][string]$Folder) {
    $sep = [IO.Path]::PathSeparator
    $scan = (@($env:PHP_INI_SCAN_DIR -split $sep) |
        Where-Object { $_ -ne $Folder }) -join $sep
    # Nothing left means it was not set before. An empty value would make
    # PHP read no ini folder at all, not even its own.
    if ($scan -eq '') {
        Remove-Item Env:PHP_INI_SCAN_DIR -ErrorAction SilentlyContinue
    } else {
        $env:PHP_INI_SCAN_DIR = $scan
    }
    $log = "$Folder/php.log"
    $lines = @(if (Test-Path $log) { Get-Content $log })
    Remove-Item -Recurse -Force $Folder
    if ($lines.Count -eq 0) { return }
    Write-Host 'PHP logged:'
    $lines | ForEach-Object { Write-Host $_ }
    if ($lines -match $PhpMessageFails) {
        throw ('PHP logged a warning, notice, deprecation or fatal error, ' +
            'which is an error.')
    }
}
