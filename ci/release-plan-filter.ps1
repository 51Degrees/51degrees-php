# Called by common-ci's steps/release-plan.ps1 with the files under a
# package's folders that changed since its last tag, once the ignore lists in
# packages.json have taken out documentation, tests and examples. Returns the
# files that count as a change to the package.
#
# A composer.json counts unless the only fields that changed are metadata
# that Packagist shows but Composer does not install by, such as the
# description and the links. A change to anything else in it, such as
# require, autoload or version, counts, as does a composer.json that was added,
# removed or cannot be read as JSON on either side.
param (
    [Parameter(Mandatory)][string]$RepoPath,
    [Parameter(Mandatory)]$Package,
    [Parameter(Mandatory)][string]$From,
    [string[]]$Files = @()
)
$ErrorActionPreference = 'Stop'

# The composer.json fields whose change alone does not release a package.
$MetadataFields = @(
    'description', 'keywords', 'homepage', 'support', 'authors', 'license')

# The composer.json at a commit as compressed JSON with the metadata fields
# taken out, or $null when the commit has no such file or it is not a JSON
# object.
function Get-ManifestWithoutMetadata([string]$Commit, [string]$File) {
    # A missing file makes git exit 128, which is an answer here rather than a
    # failure. Set for this function only.
    $PSNativeCommandUseErrorActionPreference = $false
    $text = git -C $RepoPath show "${Commit}:$File" 2>$null
    $found = $LASTEXITCODE -eq 0
    $global:LASTEXITCODE = 0
    if ($found -eq $false) {
        return $null
    }
    try {
        $manifest = ($text -join "`n") | ConvertFrom-Json
    } catch {
        return $null
    }
    if ($manifest -isnot [pscustomobject]) {
        return $null
    }
    foreach ($field in $MetadataFields) {
        $manifest.PSObject.Properties.Remove($field)
    }
    return $manifest | ConvertTo-Json -Depth 100 -Compress
}

foreach ($file in $Files) {
    if ((Split-Path -Leaf $file) -ne 'composer.json') {
        $file
        continue
    }
    $before = Get-ManifestWithoutMetadata $From $file
    $after = Get-ManifestWithoutMetadata 'HEAD' $file
    if ($null -eq $before -or $null -eq $after -or $before -cne $after) {
        $file
    }
}
