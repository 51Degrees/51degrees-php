param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
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

    # The build compiles the committed wrapper, which configure copies from
    # src/php8. A wrapper from another SWIG would join the Device Detection
    # extension's SWIG type table in a PHP that loads both, which crashes PHP.
    phpize || $(throw "phpize failed")
    ./configure --enable-FiftyOneDegreesIpIntelligenceEngine || $(throw "configure failed")
    make "-j$([Environment]::ProcessorCount)" || $(throw "make failed")
} finally {
    Pop-Location
}

# The extension builds its engine once, when PHP starts, from the free Lite
# data file that fetch-assets links into the package's assets folder, as
# OnPremise.md says to set it up. The examples use that engine.
@"
extension = "$PWD/$RepoName/on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so"
FiftyOneDegreesIpIntelligenceEngine.data_file = "$PWD/$RepoName/assets/51Degrees-LiteIpiV41.ipi"
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

# The pipeline packages come from this checkout rather than Packagist, so the
# tests run with the pipeline code they are released with. A copy of
# composer.json gets the path repositories, and the published one is left as
# it is.
$composer = Get-Content -Raw $RepoName/composer.json | ConvertFrom-Json -AsHashtable
$composer['repositories'] = @(foreach ($name in 'core', 'engines', 'cloudrequestengine') {
    @{ type = 'path'; url = "../pipeline/$name"; options = @{ symlink = $true;
        versions = @{ "51degrees/fiftyone.pipeline.$name" = '4.x-dev' } } }
}) + @($composer['repositories'])
$composer['minimum-stability'] = 'dev'
$composer['prefer-stable'] = $true
$composer | ConvertTo-Json -Depth 20 | Set-Content $RepoName/composer.ci.json
$env:COMPOSER = "composer.ci.json"
./php/build-project.ps1 -RepoName $RepoName

exit $LASTEXITCODE
