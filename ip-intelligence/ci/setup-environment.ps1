param (
    [Parameter(Mandatory=$true)]
    [string]$LanguageVersion = "8.2"
)

# Set up the PHP environment for the build. Delegates to the shared common-ci
# php steps. Mirrors device-detection-php-onpremise/ci/setup-environment.ps1.

./php/setup-environment.ps1 -LanguageVersion $LanguageVersion
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

exit 0
