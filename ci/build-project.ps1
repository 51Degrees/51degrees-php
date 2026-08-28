param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [string]$ProjectDir = ".",
    [string]$OrgName = "51Degrees",
    [string]$TranslationRepoName = "pipeline-php-translation"
)

# Build the on-premise IP intelligence native extension, then run the standard
# PHP project build. Mirrors device-detection-php-onpremise/ci/build-project.ps1.

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

# The translation base package is not published to Packagist yet, so
# composer.json resolves 51degrees/fiftyone.pipeline.translation through a path
# repository pointing at a sibling checkout. Nothing else provides it, so clone
# it here (git is already configured with the CI token by this point). Remove
# this step and the path repository from composer.json once the package is
# published and the requirement can be pinned to a real constraint.
if (-not (Test-Path $TranslationRepoName)) {
    Write-Output "Cloning $OrgName/$TranslationRepoName (translation base package)"
    git clone --depth 1 "https://github.com/$OrgName/$TranslationRepoName" $TranslationRepoName || $(throw "failed to clone $OrgName/$TranslationRepoName")
}

$env:COMPOSER = "composer.json"
./php/build-project.ps1 -RepoName $RepoName -ProjectDir $ProjectDir

exit $LASTEXITCODE
