param (
    [Parameter(Mandatory = $true)]
    [string]$RepoName
)

# The Live suite is not run here. It calls the cloud service for a
# probabilistic 51Did, which is a paid feature, once for each resource key in
# its data provider, and each call costs uses against the subscription behind
# the key. So it runs in cloud-51did.yml, on a pull request that changes the
# package and nightly, rather than in every entry of the build matrix.
Write-Output "The live cloud tests run in cloud-51did.yml, once for each resource key"
