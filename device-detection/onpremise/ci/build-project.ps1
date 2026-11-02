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

# This script only runs in nightly-pr-to-main workdflow, where we should use
# development versions of dependencies
$env:COMPOSER = "composer.json"

./php/build-project.ps1 -RepoName $RepoName

exit $LASTEXITCODE
