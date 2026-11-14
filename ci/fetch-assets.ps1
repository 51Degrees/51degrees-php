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

# common-ci's prefetch_assets action keys each day's asset cache on the text
# of this file alone, not on the package scripts it runs. So this list of
# every asset those scripts fetch keeps the key in step with them. Change it
# whenever a package's list changes, or the cache restored for the rest of
# that day lacks the new asset and every job fetches it again.
#   device-detection/cloud: 51Degrees.csv
#   device-detection/onpremise: TAC-HashV41.hash, 20000 User Agents.csv,
#     51Degrees-LiteV4.1.hash
#   ip-intelligence: 51Degrees-LiteIpiV41.ipi, 51Degrees-IPIV4AsnIpiV41.ipi,
#     51Degrees-EnterpriseIpiV41.ipi

# The package scripts fetch into ./assets, which they share, and skip a file
# that is already there, so the TAC data file is downloaded once for both
# device detection packages.
# There is no -RepoName here, and the package scripts take none, so the
# packages are found from this script's own folder.
Invoke-PackageScript -RepoName (Resolve-Path "$PSScriptRoot/..").Path -Script fetch-assets -Parameters ([hashtable]$PSBoundParameters)
