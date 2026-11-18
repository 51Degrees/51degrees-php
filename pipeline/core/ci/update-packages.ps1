param (
    [Parameter(Mandatory)][string]$RepoName,
    [Parameter(Mandatory)][string]$OrgName,
    [bool]$DryRun = $false
)
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $true

# The templates come from the javascript-templates submodule at the
# repository root. The package keeps a committed copy because Packagist serves
# GitHub's archive of a tag, which leaves a submodule empty. The submodule
# moves to the head of javascript-templates first, so the copy and the commit
# it came from change in the same pull request.
$root = Join-Path $RepoName '../..'
$source = Join-Path $root 'javascript-templates'

Write-Host "Moving javascript-templates to the head of its default branch..."
git -C $root submodule update --init --remote javascript-templates

Write-Host "Copying the templates into the package directory..."
Copy-Item -Path (Join-Path $source '*.mustache') -Destination "$RepoName/javascript-templates" -Force
