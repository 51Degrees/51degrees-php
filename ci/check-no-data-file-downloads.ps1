param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)
$ErrorActionPreference = 'Stop'

# The data repository, cxx/data, holds a .link file for each free data file.
# The packages' ci/fetch-assets.ps1 scripts link the run's cached copy beside
# it, so a file there that is not a link was downloaded by a test or an example
# during this job, and every job of the matrix downloads it again.
$data = Join-Path $RepoName 'cxx/data'
if (-not (Test-Path $data)) {
    Write-Host "There is no data repository at $data, so there is nothing to check"
    return
}

$downloaded = @(foreach ($link in Get-ChildItem -Recurse -File -Filter '*.link' $data) {
    $file = $link.FullName -replace '\.link$', ''
    if ((Test-Path -LiteralPath $file) -and -not (Get-Item -LiteralPath $file).LinkType) {
        [IO.Path]::GetRelativePath((Resolve-Path $RepoName).Path, $file)
    }
})

foreach ($file in $downloaded) {
    Write-Host "::error title=Data file downloaded in a job::$file was downloaded during this job. Add its asset to the package's ci/fetch-assets.ps1 and link the cached copy there, so that every job takes it from the run's asset cache."
}
if ($downloaded) {
    throw "These data files were downloaded during this job, not taken from the asset cache: $($downloaded -join ', ')"
}
Write-Host "No data file was downloaded during this job"
