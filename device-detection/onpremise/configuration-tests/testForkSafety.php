<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

/**
 * Fork-safety reproduction for issue #38.
 *
 * This is the test that proves why MaxPerformance (in memory) is the only safe
 * profile for PHP on premise. PHP is normally run under a process manager
 * (Apache MPM prefork, php-fpm) that serves each request from a worker process
 * forked from the one the engine was created in.
 *
 * A streaming profile (Balanced, LowMemory, HighPerformance, Default) keeps the
 * data file open and reads from it on demand through a pool of file handles.
 * When the worker is forked, those open handles are inherited by every child,
 * so the parent and the children share the same kernel file descriptions and
 * the same file read positions. Concurrent reads then move each other's file
 * position and return corrupt data, which shows up as wrong detection results
 * or a thrown exception.
 *
 * The MaxPerformance profile loads the whole data file into memory and keeps no
 * open handle, so there is nothing to share across a fork and detection stays
 * correct.
 *
 * The test builds each kind of engine directly (bypassing the module which now
 * forces MaxPerformance), forks several workers that all share the inherited
 * handles, and checks the outcome:
 *   - streaming  -> keeps >= 1 open data-file handle AND corrupts under fork
 *   - in memory  -> keeps 0 open data-file handles AND stays correct under fork
 *
 * Exit code 0 means the contrast was demonstrated. Any other code means the
 * expected behaviour was not observed.
 *
 * Inputs (env vars, with argv fallbacks):
 *   _51DEGREES_DD_PATH  path to a .hash data file   (argv[1], or the module's configured data_file)
 *   _51DEGREES_UA_CSV     path to a CSV/text file of User-Agents, one per line  (argv[3])
 *
 * Without the User-Agents file the test refuses to run, with exit code 2, as
 * for a missing data file, because a few User-Agents do not always show the
 * streaming profile's wrong results, so the outcome would come and go.
 *
 * The names these replaced are still read, as deprecated names, when the
 * variable is not set.
 */

const CHILDREN = 8;     // forked workers that all share the inherited handles
const ITERATIONS = 40;  // passes over the User-Agent list per worker
// The streaming corruption is a race between the workers. On a runner with
// few cores one round sometimes passes without a collision, so the streaming
// round is repeated until corruption is seen, up to this many times.
const STREAMING_ROUNDS = 6;
const CRASH_WEIGHT = 250; // a worker killed by a signal is the strongest possible corruption signal
// A round of workers normally ends in well under a minute. A worker still
// running after this many seconds is stuck, so it is killed and counted
// rather than left to hold the job until the workflow's own time limit.
const WORKER_TIME_LIMIT = 300;

function fail(string $message, int $code = 2): void
{
    fwrite(STDERR, "testForkSafety: {$message}\n");
    exit($code);
}

/**
 * The value of an environment variable, read by its name and, when that is
 * not set or is empty, by the deprecated name it replaced, whose use is
 * reported on standard error.
 */
function readVariable(string $name, string $deprecated): string
{
    $value = (string) getenv($name);
    if ($value !== '') {
        return $value;
    }

    $value = (string) getenv($deprecated);
    if ($value !== '') {
        fwrite(STDERR, "A value was read from the environment variable {$deprecated}, " .
            "which is deprecated. {$name} replaces it, so set that to the same value.\n");
    }

    return $value;
}

$dataFile = readVariable('_51DEGREES_DD_PATH', 'FIFTYONE_DATA_FILE') ?: ($argv[1] ?? '');
$uaCsv = readVariable('_51DEGREES_UA_CSV', 'FIFTYONE_UA_CSV') ?: ($argv[3] ?? '');
if ($uaCsv === '' || !is_file($uaCsv)) {
    fail("User-Agents file not found (set _51DEGREES_UA_CSV or pass it as the third argument): '{$uaCsv}'");
}

// The extension defines the engine classes itself, so no wrapper file is
// loaded for this variable.
if ((string) getenv('FIFTYONE_WRAPPER') !== '') {
    fwrite(STDERR, 'The environment variable FIFTYONE_WRAPPER is deprecated and ' .
        "nothing replaces it. No wrapper file is loaded, so stop setting it.\n");
}

if ($dataFile === '' && function_exists('ini_get')) {
    $dataFile = (string) ini_get('FiftyOneDegreesHashEngine.data_file');
}
if ($dataFile === '' || !is_file($dataFile)) {
    fail("data file not found (set _51DEGREES_DD_PATH or pass it as the first argument): '{$dataFile}'");
}

if (!function_exists('pcntl_fork')) {
    fail('the pcntl extension is required to reproduce the fork behaviour');
}
if (!class_exists('EngineHashSwig') || !class_exists('ConfigHashSwig')) {
    fail('the FiftyOneDegreesHashEngine extension must be loaded');
}

// Load a set of User-Agents to detect. The exact values do not matter, only
// that there are enough distinct ones to exercise the data file.
$userAgents = [];
$handle = fopen($uaCsv, 'r');
while (($line = fgets($handle)) !== false && count($userAgents) < 200) {
    $line = trim($line, " \t\r\n\"");
    if ($line !== '' && stripos($line, 'user-agent') !== 0) {
        $userAgents[] = $line;
    }
}
fclose($handle);
if (count($userAgents) === 0) {
    fail("the User-Agents file holds no User-Agents: '{$uaCsv}'");
}

/**
 * Build an engine directly with the requested profile so the test can exercise
 * a streaming configuration even though the module itself now forces
 * MaxPerformance.
 */
function buildEngine(string $dataFile, string $profile): EngineHashSwig
{
    $config = new ConfigHashSwig();
    switch ($profile) {
        case 'MaxPerformance':
            $config->setMaxPerformance();
            break;
        case 'HighPerformance':
            $config->setHighPerformance();
            break;
        case 'LowMemory':
            $config->setLowMemory();
            break;
        case 'Balanced':
        default:
            $config->setBalanced();
            break;
    }
    // A single handle maximises the contention between forked workers, which
    // makes the streaming corruption reliable rather than occasional.
    $config->setConcurrency(1);
    // Return a result for unmatched User-Agents instead of throwing, so that a
    // consistent non-match is not mistaken for a corrupt read.
    $config->setAllowUnmatched(true);

    return new EngineHashSwig($dataFile, $config, new RequiredPropertiesConfigSwig());
}

/**
 * Count the open operating-system handles that point at the data file. A
 * streaming engine keeps at least one. An in-memory engine keeps none.
 *
 * The open handles are listed under /proc on Linux. Other platforms (for
 * example macOS) do not have it, so the count cannot be determined there and -1
 * is returned to mean "unknown". The corruption check below works everywhere
 * and is the cross-platform proof.
 */
function openDataFileHandles(string $dataFile): int
{
    if (!is_dir('/proc/self/fd')) {
        return -1;
    }
    $target = realpath($dataFile);
    $count = 0;
    foreach (glob('/proc/self/fd/*') ?: [] as $fd) {
        $link = @readlink($fd);
        if ($link !== false && realpath($link) === $target) {
            $count++;
        }
    }
    return $count;
}

function handlesLabel(int $handles): string
{
    return $handles < 0 ? 'unknown (no /proc on this platform)' : (string) $handles;
}

/**
 * Detect every User-Agent ITERATIONS times. A given User-Agent must always
 * resolve to the same device id. Any variation, or any thrown exception, means
 * a read was corrupted. Returns the number of corruption events seen.
 */
function detectionWorker(EngineHashSwig $engine, array $userAgents): int
{
    $corruption = 0;
    $firstResult = [];
    for ($pass = 0; $pass < ITERATIONS; $pass++) {
        foreach ($userAgents as $userAgent) {
            try {
                $deviceId = $engine->process($userAgent)->getDeviceId();
                if (!isset($firstResult[$userAgent])) {
                    $firstResult[$userAgent] = $deviceId;
                } elseif ($firstResult[$userAgent] !== $deviceId) {
                    $corruption++;
                }
            } catch (\Throwable $e) {
                $corruption++;
            }
        }
    }
    return $corruption;
}

/**
 * Fork CHILDREN workers that all share the handles inherited from this process,
 * run them concurrently, and total the corruption they observe. The parent does
 * not detect, so that a worker crash (a signal) is contained and reported
 * rather than taking the whole test down.
 *
 * Each worker is waited for until WORKER_TIME_LIMIT seconds after the round
 * started. A worker still running then is killed and added to $stuck, which
 * fails the test, since a corrupt read must end in a wrong answer, an
 * exception or a crash, never in a worker that does not end.
 */
function runForkedWorkers(EngineHashSwig $engine, array $userAgents, int &$stuck): int
{
    $pids = [];
    for ($i = 0; $i < CHILDREN; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            fail('pcntl_fork failed');
        }
        if ($pid === 0) {
            $corruption = detectionWorker($engine, $userAgents);
            exit(min($corruption, CRASH_WEIGHT - 1));
        }
        $pids[] = $pid;
    }

    $total = 0;
    $deadline = microtime(true) + WORKER_TIME_LIMIT;
    foreach ($pids as $pid) {
        $status = 0;
        while (($waited = pcntl_waitpid($pid, $status, WNOHANG)) === 0) {
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(100000);
        }
        if ($waited === 0) {
            // Still running at the time limit, so kill it and collect it.
            fwrite(STDERR, "testForkSafety: worker {$pid} was still running after " .
                WORKER_TIME_LIMIT . " seconds and was killed\n");
            killWorker($pid);
            pcntl_waitpid($pid, $status);
            $stuck++;
            continue;
        }
        if ($waited === -1) {
            fail("pcntl_waitpid failed for worker {$pid}");
        }
        if (pcntl_wifexited($status)) {
            $total += pcntl_wexitstatus($status);
        } elseif (pcntl_wifsignaled($status)) {
            // Killed by a signal (for example a segfault from a corrupt read).
            $total += CRASH_WEIGHT;
        }
    }
    return $total;
}

/**
 * Kill a worker that has not ended. posix_kill is used where the posix
 * extension is loaded, and the kill command otherwise.
 */
function killWorker(int $pid): void
{
    if (function_exists('posix_kill')) {
        posix_kill($pid, SIGKILL);
    } else {
        exec('kill -9 ' . $pid);
    }
}

echo "Fork-safety reproduction (issue #38)\n";
echo 'Data file: ' . $dataFile . "\n";
echo 'User-Agents: ' . count($userAgents) . ', workers: ' . CHILDREN . ', iterations: ' . ITERATIONS . "\n\n";

// --- Streaming (non in-memory) profile: expected to be unsafe across a fork ---
$streaming = buildEngine($dataFile, 'Balanced');
$streamingHandles = openDataFileHandles($dataFile);
$streamingCorruption = 0;
$streamingStuck = 0;
$streamingRounds = 0;
while ($streamingCorruption < 1 && $streamingRounds < STREAMING_ROUNDS) {
    $streamingCorruption += runForkedWorkers($streaming, $userAgents, $streamingStuck);
    $streamingRounds++;
}
echo "Streaming (Balanced):\n";
echo '  open data-file handles: ' . handlesLabel($streamingHandles) . "\n";
echo "  corruption events across fork: {$streamingCorruption} (in {$streamingRounds} round(s))\n";
echo "  workers killed at the time limit: {$streamingStuck}\n\n";
unset($streaming);
gc_collect_cycles();

// --- In-memory (MaxPerformance) profile: expected to be safe across a fork ---
$inMemory = buildEngine($dataFile, 'MaxPerformance');
$inMemoryHandles = openDataFileHandles($dataFile);
$inMemoryStuck = 0;
$inMemoryCorruption = runForkedWorkers($inMemory, $userAgents, $inMemoryStuck);
echo "In memory (MaxPerformance):\n";
echo '  open data-file handles: ' . handlesLabel($inMemoryHandles) . "\n";
echo "  corruption events across fork: {$inMemoryCorruption}\n";
echo "  workers killed at the time limit: {$inMemoryStuck}\n\n";
unset($inMemory);
gc_collect_cycles();

$failures = [];
// Handle counts are only asserted where they can be determined (Linux). A
// value of -1 means "unknown" and is skipped.
if ($streamingHandles === 0) {
    $failures[] = 'expected the streaming engine to keep at least one open data-file handle';
}
if ($inMemoryHandles > 0) {
    $failures[] = "expected the in-memory engine to keep no open data-file handle, found {$inMemoryHandles}";
}
// The corruption checks work on every platform and are the core proof.
if ($streamingCorruption < 1) {
    $failures[] = 'expected the streaming profile to corrupt under fork, but no corruption was observed in ' . STREAMING_ROUNDS . ' rounds';
}
if ($inMemoryCorruption !== 0) {
    $failures[] = "expected the in-memory profile to be fork-safe, but observed {$inMemoryCorruption} corruption events";
}
// A worker that did not end is a defect in either profile: a corrupt read
// must give a wrong answer, an exception or a crash, which are counted above.
if ($streamingStuck > 0) {
    $failures[] = "{$streamingStuck} streaming worker(s) did not end within " . WORKER_TIME_LIMIT . ' seconds and were killed';
}
if ($inMemoryStuck > 0) {
    $failures[] = "{$inMemoryStuck} in-memory worker(s) did not end within " . WORKER_TIME_LIMIT . ' seconds and were killed';
}

if (count($failures) > 0) {
    echo "FAIL\n - " . implode("\n - ", $failures) . "\n";
    exit(1);
}

echo "PASS\n";
echo "A streaming profile keeps open file handles that are shared across a fork and corrupt reads.\n";
echo "The in-memory MaxPerformance profile keeps no file handles and is fork-safe.\n";
echo "This is why MaxPerformance (in memory) is the only safe profile for PHP on premise.\n";
exit(0);
