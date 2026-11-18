param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

# The unit suite runs the examples with no resource key, so it runs on every
# build, whether or not a key is given.
./php/run-unit-tests.ps1 -RepoName $RepoName

exit $LASTEXITCODE
