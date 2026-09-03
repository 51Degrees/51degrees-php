param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$Version
)

# Called by nightly-publish.package.ps1 and nightly-publish.test.ps1.
#
# There is no package to install for a Composer library, so this rebuilds the
# project in place - which for this repository also builds the native
# on-premise extension and clones the translation base package that
# composer.json resolves through a path repository. Mirrors
# device-detection-php-onpremise. $Version is unused but must be declared:
# steps/run-script.ps1 supplies it from $Options and the publish flow expects
# the parameter to be accepted.

& ./$RepoName/ci/build-project.ps1 -RepoName $RepoName
