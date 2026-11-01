param (
    # The prefetch_assets action calls this with the workflow secrets and
    # nothing else, so every parameter must be optional and named after a
    # secret.
    [string]$DeviceDetection,
    [string]$DeviceDetectionUrl,
    [string]$CsvUrl,
    [string]$IpIntelligenceUrl
)
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# The package scripts fetch into ./assets, which they share, and skip a file
# that is already there, so the TAC data file is downloaded once for both
# device detection packages.
# There is no -RepoName here, and the package scripts take none, so the
# packages are found from this script's own folder.
Invoke-PackageScript -RepoName (Resolve-Path "$PSScriptRoot/..").Path -Script fetch-assets -Parameters ([hashtable]$PSBoundParameters)
