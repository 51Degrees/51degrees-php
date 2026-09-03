param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$OrgName,
    [bool]$DryRun = $false
)

# Called unconditionally by nightly-package-update.ps1, which then runs
# steps/has-changed.ps1 and raises a pull request only if this script changed
# something in the working tree.
#
# Its absence failed the Nightly Package Update job outright ("The term
# './ip-intelligence-php/ci/update-packages.ps1' is not recognized..."). Note
# that device-detection-php-onpremise has no such script because its
# nightly-pipeline.yml has no PackageUpdate job at all; this repository enables
# that job, so it needs the script.
#
# There is deliberately nothing to update here. This repository's PHP
# dependencies are Composer version constraints ("4.*") in composer.json,
# resolved afresh on every install, and no composer.lock is committed - so
# there is no pinned version in the tree for a nightly to bump. The native
# dependency, ip-intelligence-cxx, is a submodule and is updated by the
# separate SubmoduleUpdate job.
#
# Leaving the tree untouched makes has-changed.ps1 report no changes, so the
# job logs "No package changes, so not creating a pull request" and passes.
#
# If a composer.lock is ever committed, or javascript-templates are vendored in
# the way pipeline-php-core does, this is where that refresh belongs.

Write-Output "No package dependencies to update: composer.json pins version constraints only and no composer.lock is committed."
