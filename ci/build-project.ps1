param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [string]$ProjectDir = ".",
    [string]$OrgName = "51Degrees",
    [string]$TranslationRepoName = "pipeline-php-translation"
)

Push-Location $RepoName/on-premise
try {
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
# repository pointing at a sibling checkout, and nothing else provides it.
if (-not (Test-Path $TranslationRepoName)) {
    Write-Output "Cloning $OrgName/$TranslationRepoName (translation base package)"
    git clone --depth 1 "https://github.com/$OrgName/$TranslationRepoName" $TranslationRepoName || $(throw "failed to clone $OrgName/$TranslationRepoName")
}

$env:COMPOSER = "composer.json"
./php/build-project.ps1 -RepoName $RepoName -ProjectDir $ProjectDir

exit $LASTEXITCODE
