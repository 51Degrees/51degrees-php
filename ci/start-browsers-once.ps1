# Starts Chrome, Firefox and their drivers once, waits for them to finish and
# prints how long each took. The first start of a browser on a fresh runner
# takes many times longer than the starts after it, so the Selenium contract
# tests run this first and that cost falls outside the limits of their tests.
# A start that takes longer than the limit is stopped, and one that exits with
# an error is reported with its error output, both as warnings, and the tests
# then run as they would have without it.
param(
    # The longest each first start is given.
    [int]$LimitSeconds = 300
)
$ErrorActionPreference = "Stop"

# The first of the names in a folder on the path, taking each folder in turn,
# as the contract tests search.
function Find-OnPath {
    param([string[]]$Names)
    foreach ($folder in ($env:PATH -split [System.IO.Path]::PathSeparator)) {
        if (-not $folder) {
            continue
        }
        foreach ($name in $Names) {
            $candidate = Join-Path $folder $name
            if (Test-Path $candidate -PathType Leaf) {
                return $candidate
            }
        }
    }
    return $null
}

# The browser the contract tests start, being the file the variable names,
# then the first of the names on the path. When neither gives one the tests
# leave the driver to find the browser where it installs itself, so that place
# is used here.
function Find-Browser {
    param([string]$Variable, [string[]]$Names, [string]$MacOS, [string]$Windows)
    $configured = [Environment]::GetEnvironmentVariable($Variable)
    if ($configured -and (Test-Path $configured -PathType Leaf)) {
        return $configured
    }
    $onPath = Find-OnPath $Names
    if ($onPath) {
        return $onPath
    }
    $installed = if ($IsMacOS) {
        $MacOS
    } elseif ($IsWindows) {
        Join-Path ([Environment]::GetFolderPath('ProgramFiles')) $Windows
    }
    if ($installed -and (Test-Path $installed -PathType Leaf)) {
        return $installed
    }
    return $null
}

# The driver the contract tests start, being the file the variable names or
# the driver in the folder it names, then the first driver on the path.
function Find-Driver {
    param([string]$Variable, [string[]]$Names)
    $configured = [Environment]::GetEnvironmentVariable($Variable)
    if ($configured) {
        if (Test-Path $configured -PathType Leaf) {
            return $configured
        }
        foreach ($name in $Names) {
            $candidate = Join-Path $configured $name
            if (Test-Path $candidate -PathType Leaf) {
                return $candidate
            }
        }
    }
    return Find-OnPath $Names
}

# Runs a program, stopping it after the limit. Gives the seconds it took, its
# exit code, or -1 when it was stopped, and the start of its error output.
function Measure-Start {
    param([string]$FilePath, [string[]]$Arguments)
    $output = [System.IO.Path]::GetTempFileName()
    $errors = [System.IO.Path]::GetTempFileName()
    try {
        $watch = [System.Diagnostics.Stopwatch]::StartNew()
        $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -PassThru -NoNewWindow `
            -RedirectStandardOutput $output -RedirectStandardError $errors
        # Reading the handle now keeps the exit code readable after the exit.
        $null = $process.Handle
        $exitCode = -1
        if ($process.WaitForExit($LimitSeconds * 1000)) {
            $process.WaitForExit()
            $exitCode = $process.ExitCode
        } else {
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
        }
        return [pscustomobject]@{
            Seconds  = [math]::Round($watch.Elapsed.TotalSeconds, 1)
            ExitCode = $exitCode
            Errors   = ((Get-Content $errors -TotalCount 5 -ErrorAction SilentlyContinue) -join ' ')
        }
    } finally {
        Remove-Item $output, $errors -Force -ErrorAction SilentlyContinue
    }
}

# Prints the result of one start, as a warning unless it exited cleanly.
function Write-Start {
    param([string]$Browser, [string]$What, $Result)
    if ($Result.ExitCode -eq 0) {
        Write-Host "Started $What once before the Selenium contract tests in $($Result.Seconds) s"
    } elseif ($Result.ExitCode -eq -1) {
        Write-Host "::warning title=$Browser slow to start::$What did not finish its first start within $LimitSeconds s, so it was stopped."
    } else {
        Write-Host "::warning title=$Browser did not start::$What exited with code $($Result.ExitCode) after $($Result.Seconds) s. $($Result.Errors)"
    }
}

# Chrome loads a blank page with a profile of its own and exits.
$driver = Find-Driver 'CHROMEWEBDRIVER' 'chromedriver', 'chromedriver.exe'
if ($driver) {
    Write-Start 'Chrome' "the Chrome driver $driver" (Measure-Start -FilePath $driver -Arguments '--version')
}
$chrome = Find-Browser 'CHROME_BIN' 'google-chrome', 'google-chrome-stable', 'chromium-browser', 'chromium', 'chrome.exe' `
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' 'Google\Chrome\Application\chrome.exe'
if ($chrome) {
    $userDataDir = Join-Path ([System.IO.Path]::GetTempPath()) "chrome-first-start-$PID"
    try {
        Write-Start 'Chrome' "Chrome $chrome" (Measure-Start -FilePath $chrome -Arguments @(
            '--headless', '--disable-gpu', '--no-first-run', '--use-mock-keychain',
            "--user-data-dir=$userDataDir", '--dump-dom', 'about:blank'))
    } finally {
        Remove-Item -Recurse -Force $userDataDir -ErrorAction SilentlyContinue
    }
} else {
    Write-Host "::warning title=Chrome not started::No Chrome was found to start before the Selenium contract tests."
}

# Firefox takes a screenshot of a blank page with a profile of its own and
# exits. On Windows the program started hands over to the browser and exits at
# once, unless it is asked to wait for the browser.
$driver = Find-Driver 'GECKOWEBDRIVER' 'geckodriver', 'geckodriver.exe'
if ($driver) {
    Write-Start 'Firefox' "the Firefox driver $driver" (Measure-Start -FilePath $driver -Arguments '--version')
}
$firefox = Find-Browser 'FIREFOX_BIN' 'firefox', 'firefox.exe' `
    '/Applications/Firefox.app/Contents/MacOS/firefox' 'Mozilla Firefox\firefox.exe'
if ($firefox) {
    $profileDir = Join-Path ([System.IO.Path]::GetTempPath()) "firefox-first-start-$PID"
    $screenshot = Join-Path ([System.IO.Path]::GetTempPath()) "firefox-first-start-$PID.png"
    New-Item -ItemType Directory -Path $profileDir -Force | Out-Null
    $arguments = @('--headless', '--no-remote', '--profile', $profileDir, '--screenshot', $screenshot, 'about:blank')
    if ($IsWindows) {
        $arguments = @('--wait-for-browser') + $arguments
    }
    try {
        Write-Start 'Firefox' "Firefox $firefox" (Measure-Start -FilePath $firefox -Arguments $arguments)
    } finally {
        Remove-Item -Recurse -Force $profileDir, $screenshot -ErrorAction SilentlyContinue
    }
} else {
    Write-Host "::warning title=Firefox not started::No Firefox was found to start before the Selenium contract tests."
}
