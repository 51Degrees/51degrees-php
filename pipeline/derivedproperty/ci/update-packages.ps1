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
