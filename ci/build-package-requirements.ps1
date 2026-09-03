# Called unconditionally by nightly-publish.prebuild.ps1, after
# ci/setup-environment.ps1. There is nothing to prepare ahead of packaging a
# Composer library - the repository is the package - but common-ci invokes the
# script by name, so it has to exist. Mirrors device-detection-php-onpremise.

Write-Output "No pre-build requirements needed"
