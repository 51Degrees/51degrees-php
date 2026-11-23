param (
    [Parameter(Mandatory=$true)]
    [string]$LanguageVersion
)

# Every package's setup-environment.ps1 makes this same call, so it runs once.
./php/setup-environment.ps1 -LanguageVersion $LanguageVersion
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

exit 0
