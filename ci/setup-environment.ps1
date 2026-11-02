param (
    [Parameter(Mandatory=$true)]
    [string]$LanguageVersion
)

# Every package's setup-environment.ps1 makes this same call, so it runs once.
./php/setup-environment.ps1 -LanguageVersion $LanguageVersion
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

# ip-intelligence generates its SWIG wrapper when it builds, so swig has to
# be on the PATH. The Ubuntu image ships it and the macOS image does not. On
# Windows the extensions are not built, so swig is not needed there.
if (-not $IsWindows -and -not (Get-Command swig -ErrorAction SilentlyContinue)) {
    if (-not $IsMacOS) {
        throw "swig is not on the PATH and this image is not macOS"
    }
    Write-Host "Installing swig with brew"
    brew install swig
    if ($LASTEXITCODE -ne 0) {
        throw "failed to install swig"
    }
}

exit 0
