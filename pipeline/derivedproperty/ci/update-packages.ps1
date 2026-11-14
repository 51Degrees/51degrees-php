param (
    [Parameter(Mandatory)][string]$RepoName,
    [Parameter(Mandatory)][string]$OrgName,
    [bool]$DryRun = $false
)
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $true

# The scripts come from the scripts folder of the derived-properties
# submodule at the repository root. The package keeps a committed copy
# because Packagist serves GitHub's archive of a tag, which leaves a
# submodule empty. The submodule moves to the head of derived-properties
# first, so the copy and the commit it came from change in the same pull
# request. tests/BuiltInScriptsTests.php fails when the two differ.
$root = Join-Path $RepoName '../..'
$source = Join-Path $root 'derived-properties' 'scripts'
$copy = Join-Path $RepoName 'scripts'

Write-Host "Moving derived-properties to the head of its default branch..."
git -C $root submodule update --init --remote derived-properties

Write-Host "Copying the scripts into the package directory..."
$extensions = @('.yaml', '.yml', '.json')
Get-ChildItem -Path $copy -File |
    Where-Object { $extensions -contains $_.Extension.ToLowerInvariant() } |
    Remove-Item
Get-ChildItem -Path $source -File |
    Where-Object { $extensions -contains $_.Extension.ToLowerInvariant() } |
    Copy-Item -Destination $copy

# The compiled scripts are written from the copy, so that building the
# element reads no YAML for a built in script. tests/BuiltInScriptsTests.php
# fails when a compiled script is not the script file beside it. The
# generator needs an autoloader, so a package install is made when there is
# none and removed afterwards, as a package vendor folder stops the root
# build.
Write-Host "Compiling the built in scripts..."
Push-Location $RepoName
$installed = $false
try {
    if (-not (Test-Path 'vendor/autoload.php') -and
        -not (Test-Path '../../vendor/autoload.php')) {
        composer install --no-dev --no-interaction --no-progress
        $installed = $true
    }
    php ci/compile-built-in-scripts.php
} finally {
    if ($installed) {
        Remove-Item -Recurse -Force 'vendor', 'composer.lock' -ErrorAction SilentlyContinue
    }
    Pop-Location
}
