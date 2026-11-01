param (
    [Parameter(Mandatory=$true)]
    [string]$Version
)

# Nothing is uploaded. Packagist reads the mirrors, not this repository, and
# makes a release from each version tag it finds on them. common-ci tags this
# repository after this script, and .github/workflows/mirror.yml and
# did-publish.yml put that tag on every mirror.
Write-Output "Packagist picks up $Version from the mirrors once mirror.yml and did-publish.yml have tagged them"
