param (
    [Parameter(Mandatory=$true)]
    [string]$RepoName,
    [Parameter(Mandatory=$true)]
    [string]$Name,
    [boolean]$RunPerformance = $True
)

# Not implemented: ComparePerformance expects a results_<Name>.json summary, and
# examples/onpremise/performance-console.php only prints human readable output.
# Every ci/options.json entry sets RunPerformance to false, so this is not
# reached; it warns rather than reporting a measurement it did not take.

if (!$RunPerformance) {
    Write-Output "Skipping performance tests: RunPerformance is not set for '$Name'"
    return
}

Write-Output "::warning file=$($MyInvocation.ScriptName),line=$($MyInvocation.ScriptLineNumber),title=Performance Tests Not Implemented::RunPerformance is set for '$Name', but this repository has no machine readable benchmark yet."
