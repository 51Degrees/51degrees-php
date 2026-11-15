# PHPUnit 10 and later read test metadata from attributes, and PHPUnit 12
# reads none from doc comments, so a data provider, requirement, hook or
# process setting written only as an annotation is lost there. Each package
# allows any PHPUnit, so a package's own composer install on PHP 8.3 or later
# gets PHPUnit 12. This reads every PHP file git tracks in a tests folder and
# fails on an annotation that has no attribute of the matching name after its
# doc comment, or whose attribute this file does not import from
# PHPUnit\Framework\Attributes.
#
# Dot-sourcing the script defines Find-MissingTestAttribute without reading
# the repository, which is how its tests use it.

[CmdletBinding()]
param(
    # The root of the checkout to read. The default is the repository this
    # script lives in.
    [string]$RepoRoot = (Join-Path $PSScriptRoot '..')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:AttributeNamespace = 'PHPUnit\Framework\Attributes\'

# Each annotation and the attributes that may stand for it. `@requires` is
# keyed by its first word.
$script:AnnotationAttributes = @{
    'after'                       = @('After')
    'afterClass'                  = @('AfterClass')
    'backupGlobals'               = @('BackupGlobals')
    'backupStaticAttributes'      = @('BackupStaticProperties')
    'before'                      = @('Before')
    'beforeClass'                 = @('BeforeClass')
    'coversNothing'               = @('CoversNothing')
    'dataProvider'                = @('DataProvider', 'DataProviderExternal')
    'depends'                     = @('Depends', 'DependsExternal',
                                      'DependsUsingDeepClone',
                                      'DependsUsingShallowClone')
    'doesNotPerformAssertions'    = @('DoesNotPerformAssertions')
    'group'                       = @('Group')
    'large'                       = @('Large')
    'medium'                      = @('Medium')
    'preserveGlobalState'         = @('PreserveGlobalState')
    'requires OS'                 = @('RequiresOperatingSystem')
    'requires OSFAMILY'           = @('RequiresOperatingSystemFamily')
    'requires PHP'                = @('RequiresPhp')
    'requires PHPUnit'            = @('RequiresPhpunit')
    'requires extension'          = @('RequiresPhpExtension')
    'requires function'           = @('RequiresFunction', 'RequiresMethod')
    'requires setting'            = @('RequiresSetting')
    'runInSeparateProcess'        = @('RunInSeparateProcess')
    'runTestsInSeparateProcesses' = @('RunTestsInSeparateProcesses')
    'small'                       = @('Small')
    'test'                        = @('Test')
    'testWith'                    = @('TestWith', 'TestWithJson')
    'ticket'                      = @('Ticket')
}

$script:DocComment = [regex]::new('/\*\*.*?\*/', 'Singleline')
$script:AnnotationLine = [regex]::new(
    '^\s*\*?\s*@(\w+)(?:[ \t]+(\w+))?', 'Multiline')
# An attribute name, written plainly or with its namespace, at the start of
# an attribute group or after a comma between attributes.
$script:AttributeName = [regex]::new('(?:#\[|,)\s*(\\?(?:\w+\\)*\w+)')

# The annotations in the text that have no matching attribute, each as the
# line it is on, the annotation and the attributes that would stand for it.
function Find-MissingTestAttribute([string]$Text) {
    $imported = @{}
    foreach ($m in [regex]::Matches($Text,
            '(?m)^\s*use\s+\\?PHPUnit\\Framework\\Attributes\\(\w+)\s*;')) {
        $imported[$m.Groups[1].Value] = $true
    }

    foreach ($comment in $script:DocComment.Matches($Text)) {
        # The attributes are the lines starting #[ straight after the doc
        # comment, before the declaration it belongs to.
        $after = $Text.Substring($comment.Index + $comment.Length)
        $names = @{}
        foreach ($line in ($after -split "`r?`n" | Select-Object -Skip 1)) {
            if ($line -notmatch '^\s*#\[') {
                break
            }
            foreach ($m in $script:AttributeName.Matches($line)) {
                $name = $m.Groups[1].Value
                if ($name.StartsWith('\' + $script:AttributeNamespace)) {
                    $names[$name.Substring($script:AttributeNamespace.Length + 1)] = $true
                } elseif ($name -notmatch '\\' -and $imported.ContainsKey($name)) {
                    $names[$name] = $true
                }
            }
        }

        foreach ($m in $script:AnnotationLine.Matches($comment.Value)) {
            $tag = $m.Groups[1].Value
            if ($tag -ceq 'requires') {
                $tag = "requires $($m.Groups[2].Value)"
            }
            $wanted = $null
            foreach ($key in $script:AnnotationAttributes.Keys) {
                if ($key -ceq $tag) {
                    $wanted = $script:AnnotationAttributes[$key]
                }
            }
            if (-not $wanted) {
                continue
            }
            if (@($wanted | Where-Object { $names.ContainsKey($_) }).Count -eq 0) {
                $before = $Text.Substring(0, $comment.Index + $m.Index)
                [pscustomobject]@{
                    Line       = ([regex]::Matches($before, "`n")).Count + 1
                    Annotation = "@$tag"
                    Attributes = $wanted
                }
            }
        }
    }
}

if ($MyInvocation.InvocationName -eq '.') {
    return
}

$files = @(git -C $RepoRoot ls-files -- '*/tests/*.php' 'tests/*.php')
if ($LASTEXITCODE -ne 0) {
    throw "git ls-files failed in $RepoRoot."
}
$failed = $false
foreach ($file in $files) {
    $text = Get-Content -Raw -LiteralPath (Join-Path $RepoRoot $file)
    foreach ($problem in @(Find-MissingTestAttribute $text)) {
        $failed = $true
        $choices = ($problem.Attributes | ForEach-Object { "#[$_]" }) -join ' or '
        Write-Host ("::error file=$file,line=$($problem.Line)::" +
            "$($problem.Annotation) has no $choices imported from " +
            "PHPUnit\Framework\Attributes, so PHPUnit 12 does not see it.")
    }
}
if ($failed) {
    exit 1
}
Write-Host "Every annotation in $($files.Count) test files has its attribute."
exit 0
