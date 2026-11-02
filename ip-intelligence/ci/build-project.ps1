param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [string]$ProjectDir = ".",
    [string]$OrgName = "51Degrees",
    [string]$TranslationRepoName = "pipeline-php-translation"
)

Push-Location $RepoName/on-premise
try {
    # PHP names each object after the path of its source, so the C sources
    # must sit under on-premise rather than be listed as ../../cxx. They are
    # copied, not linked: a link would put this extension's objects in the
    # shared cxx folder, where the other extension, which builds common with
    # different defines, would pick them up.
    Remove-Item -Recurse -Force cxx -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Force -Path cxx | Out-Null
    Copy-Item -Recurse -Path "$PSScriptRoot/../../cxx/src" -Destination cxx/src -ErrorAction Stop

    # Generate the SWIG wrapper (requires SWIG 4.1+ on PATH).
    New-Item -ItemType Directory -Force -Path src/php8 | Out-Null
    swig -c++ -php -module FiftyOneDegreesIpIntelligenceEngine -outdir src/php8 -o src/php8/ipi_wrap.cxx ipi-php.i || $(throw "swig failed")

    phpize || $(throw "phpize failed")
    ./configure --enable-FiftyOneDegreesIpIntelligenceEngine || $(throw "configure failed")
    Copy-Item -Path src/php8/* -Destination . -Force
    make "-j$([Environment]::ProcessorCount)" || $(throw "make failed")
} finally {
    Pop-Location
}

@"
extension = "$PWD/$RepoName/on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so"
"@ | Out-File $RepoName/php.ini

# composer.json resolves 51degrees/fiftyone.pipeline.translation through a path
# repository pointing at pipeline/translation beside this package, and nothing
# else provides it. A standalone checkout has no such folder, so the
# translation repository is cloned there.
$translationPath = "$RepoName/../pipeline/translation"
if (-not (Test-Path $translationPath)) {
    Write-Output "Cloning $OrgName/$TranslationRepoName (translation base package)"
    git clone --depth 1 "https://github.com/$OrgName/$TranslationRepoName" $translationPath || $(throw "failed to clone $OrgName/$TranslationRepoName")
}

$env:COMPOSER = "composer.json"
./php/build-project.ps1 -RepoName $RepoName

exit $LASTEXITCODE
