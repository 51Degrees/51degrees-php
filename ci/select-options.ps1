param(
    # Every entry of options.json.
    [Parameter(Mandatory)][object[]]$Options,
    # The files the pull request changes, less documentation.
    [string[]]$ChangedFiles,
    # Those of the changed files whose change touches only comments.
    [string[]]$CommentOnlyFiles
)
# Chooses the options.json entries a pull request is built on, for common-ci's
# steps/select-options.ps1. The branch itself and a release build every entry.
#
# The standard set is PHP 8.5 and 7.4 on Linux, the newest and the oldest, and
# the only Windows entry, which between them build the native extension on
# both. The Selenium contract runs on PHP 8.5 alone. A change in more than its
# comments to a composer.json, to a ci folder or outside the package folders
# adds the other PHP versions on Linux. Only five macOS jobs run at once
# across the organisation, and the releases need them, so macOS is left to the
# branch and release builds, as is performance.

$Standard = @('Ubuntu_PHP_8.5', 'Ubuntu_PHP_7.4', 'Windows_PHP_8.3')
$Contract = 'Ubuntu_PHP_8.5'
$PackageFolders = @('pipeline/', 'device-detection/', 'ip-intelligence/',
    'location/')

function Test-Under([string]$Path, [string[]]$Prefixes) {
    return @($Prefixes | Where-Object { $Path.StartsWith($_) }).Count -gt 0
}

$code = @($ChangedFiles | Where-Object { $CommentOnlyFiles -notcontains $_ })
$versions = @($code | Where-Object {
    $_ -match '(^|/)composer\.json$' -or $_ -match '(^|/)ci/' -or
        ($_ -ne 'cxx' -and !(Test-Under $_ $PackageFolders))
}).Count -gt 0
Write-Host "Packaging, CI or files outside the package folders: $versions."

foreach ($entry in $Options) {
    $name = $entry.Name
    if ($entry.Image -like 'macos*') {
        Write-Host "$name is left to the branch and release builds"
        continue
    }
    $why = if ($Standard -contains $name) { 'the standard set' }
        elseif ($versions) { 'a change to packaging, CI or outside the packages' }
    if (!$why) {
        Write-Host "$name is not needed for this change"
        continue
    }
    $entry.SeleniumContract = $name -eq $Contract
    $entry.RunPerformance = $false
    Write-Host "$name runs for $why"
    $entry
}
