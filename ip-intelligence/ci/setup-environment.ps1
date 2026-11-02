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

# The build generates the SWIG wrapper rather than committing it, so swig has
# to be on PATH. The Ubuntu image ships it and the macOS image does not, which
# stopped every macOS job with "The term 'swig' is not recognized" and, because
# the matrix fails fast, cancelled the Ubuntu jobs with it.
if (-not (Get-Command swig -ErrorAction SilentlyContinue)) {
    if ($IsMacOS) {
        Write-Output "Installing swig with brew"
        brew install swig
        if ($LASTEXITCODE -ne 0) {
            throw "failed to install swig"
        }
    } else {
        throw "swig is not on PATH and this image is not macOS"
    }
}
Write-Output "Using $((Get-Command swig).Source)"

exit 0
