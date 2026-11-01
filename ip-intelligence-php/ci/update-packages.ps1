param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$OrgName,
    [bool]$DryRun = $false
)

# Nothing to update: composer.json holds version constraints only, no
# composer.lock is committed, and the cxx submodule is handled by
# SubmoduleUpdate.
Write-Output "No package dependencies to update"
