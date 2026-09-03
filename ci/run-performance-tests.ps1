param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$Name,
    [boolean]$RunPerformance = $True
)

# Called by nightly-pull-request.build-and-test.ps1 and nightly-publish.test.ps1,
# but only when the matrix entry sets RunPerformance. Every entry in
# ci/options.json currently sets it to false, so this script is not reached -
# it exists because common-ci resolves the path by name and a missing file
# fails the job rather than skipping it.
#
# NOT YET WIRED UP. ComparePerformance expects a results_<Name>.json summary in
# test-results/performance-summary, in the HigherIsBetter/LowerIsBetter shape
# below. examples/onpremise/performance-console.php already measures detections
# per second and average ms per detection, but prints them as human readable
# text rather than JSON, so turning performance tests on means giving that
# example a machine readable output mode first. Until then this skips loudly
# instead of pretending to have measured something.

if (!$RunPerformance) {
    Write-Output "Skipping performance tests: RunPerformance is not set for '$Name'"
    return
}

$dataFile = "assets/51Degrees-EnterpriseIpiV41.ipi"
if (!(Test-Path $dataFile)) {
    $dataFile = "assets/51Degrees-LiteIpiV41.ipi"
}
if (!(Test-Path $dataFile)) {
    Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=No On-Premise Data File::No IP intelligence data file was found, so performance tests will not run."
    return
}

Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=Performance Tests Not Implemented::RunPerformance is set for '$Name', but this repository has no machine readable benchmark yet. See the note at the top of ci/run-performance-tests.ps1."
