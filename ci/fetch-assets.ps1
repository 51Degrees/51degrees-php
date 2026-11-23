# The prefetch_assets action and the build jobs call this after common-ci has
# put the keys into the environment under their _51DEGREES_ names, so the
# package scripts read the data license from _51DEGREES_LICENSE_KEY and take
# no key as a parameter.
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/packages.ps1"

# The package scripts fetch into ./assets, which they share, and skip a file
# that is already there, so the TAC data file is downloaded once for both
# device detection packages.
# There is no -RepoName here, and the package scripts take none, so the
# packages are found from this script's own folder.
Invoke-PackageScript -RepoName (Resolve-Path "$PSScriptRoot/..").Path -Script fetch-assets -Parameters @{}
