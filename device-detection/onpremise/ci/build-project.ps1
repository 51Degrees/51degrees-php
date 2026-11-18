param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName
)

Push-Location $RepoName/on-premise
try {
    # PHP names each object after the path of its source, so the C sources
    # must sit under on-premise rather than be listed as ../../../cxx. They are
    # copied, not linked: a link would put this extension's objects in the
    # shared cxx folder, where the other extension, which builds common with
    # different defines, would pick them up.
    Remove-Item -Recurse -Force cxx -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Force -Path cxx | Out-Null
    Copy-Item -Recurse -Path "$PSScriptRoot/../../../cxx/src" -Destination cxx/src -ErrorAction Stop

    phpize || $(throw "phpize failed")
    ./configure || $(throw "configure failed")
    make "-j$([Environment]::ProcessorCount)" || $(throw "make failed")
} finally {
    Pop-Location
}

@"
extension = "$PWD/$RepoName/on-premise/modules/FiftyOneDegreesHashEngine.so"
FiftyOneDegreesHashEngine.data_file = "$PWD/assets/TAC-HashV41.hash"
FiftyOneDegreesHashEngine.allow_unmatched = false
"@ | Out-File $RepoName/php.ini

# The pipeline packages come from this checkout rather than Packagist, so the
# tests run with the pipeline code they are released with. A copy of
# composer.json gets the path repositories, and the published one is left as
# it is.
$composer = Get-Content -Raw $RepoName/composer.json | ConvertFrom-Json -AsHashtable
$composer['repositories'] = @(foreach ($name in 'core', 'engines') {
    @{ type = 'path'; url = "../../pipeline/$name"; options = @{ symlink = $true;
        versions = @{ "51degrees/fiftyone.pipeline.$name" = '4.x-dev' } } }
})
$composer['minimum-stability'] = 'dev'
$composer['prefer-stable'] = $true
$composer | ConvertTo-Json -Depth 20 | Set-Content $RepoName/composer.ci.json
$env:COMPOSER = "composer.ci.json"

./php/build-project.ps1 -RepoName $RepoName

exit $LASTEXITCODE
