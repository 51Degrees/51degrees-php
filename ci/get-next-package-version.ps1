param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$VariableName
)

# Resolve the next semantic version for the package, via GitVersion.
#
# nightly-publish.configure.ps1 calls this unconditionally, before anything else
# in the publish flow, so its absence failed the Nightly Publish Configure job
# outright ("The term './ip-intelligence-php/ci/get-next-package-version.ps1' is
# not recognized..."). Mirrors device-detection-php-onpremise.

./php/get-next-package-version.ps1 -RepoName $RepoName -VariableName $VariableName

exit $LASTEXITCODE
