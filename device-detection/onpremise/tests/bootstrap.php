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

// The bootstrap every phpunit.xml in this repository names. Each package
// has the same copy, as each is published on its own.

namespace fiftyone\pipeline\tests;

/**
 * Fails the test run when a PHP the tests start, such as an example or a
 * built-in server, reports a warning, notice, deprecation or fatal error.
 * phpunit.xml does this for the tests' own process only, as another PHP
 * takes its settings from php.ini. The tests' own process logs to the same
 * file, so a message raised where PHPUnit does not handle errors, such as
 * in setUpBeforeClass or a data provider under PHPUnit 9, fails it too.
 *
 * Each PHP started with the environment of the tests reads an ini file
 * that reports every level and logs each message to one file, which is
 * checked once the run ends. A test that sets PHP_INI_SCAN_DIR itself adds
 * folder() to it, and one that starts PHP with -n, which reads no ini file,
 * passes settings(). As PHP logs to that file rather than to standard
 * error, a test that reads PHP's own message in what a PHP wrote starts it
 * with -d display_errors=stderr.
 */
final class ChildPhp
{
    // The ini file every PHP the tests start reads.
    private const INI = 'child-php.ini';

    // The log those PHPs write their messages to.
    private const LOG = 'child-php.log';

    // The line PHP logs for a warning, notice, deprecation or fatal error.
    // Strict Standards is the E_STRICT level of PHP 7.4.
    private const FAILS = '~^\[[^]]*\] PHP (?:Warning|Notice|Deprecated|' .
        'Strict Standards|Fatal error|Parse error|Recoverable fatal error):~';

    /** @var string The folder holding the ini file and the log */
    private static $folder = '';

    /**
     * Writes the ini file and adds its folder to PHP_INI_SCAN_DIR, so that
     * each PHP started from here reads it, and checks the log when this
     * process ends. In a process PHPUnit starts to run a test in, the
     * folder is already there, and the process that started it checks.
     */
    public static function watch(): void
    {
        $scan = getenv('PHP_INI_SCAN_DIR');
        foreach (explode(PATH_SEPARATOR, (string) $scan) as $folder) {
            if ($folder !== '' && is_file($folder . '/' . self::INI)) {
                self::$folder = $folder;
                self::logHere();
                return;
            }
        }

        $folder = sys_get_temp_dir() . '/child-php-' . bin2hex(random_bytes(8));
        if (!mkdir($folder)) {
            throw new \RuntimeException("{$folder} could not be made.");
        }
        // A backslash in a quoted ini value can start an escape, so the
        // path has forward slashes, which PHP takes on every platform.
        $folder = str_replace('\\', '/', $folder);
        self::$folder = $folder;
        file_put_contents(
            $folder . '/' . self::INI,
            "error_reporting = -1\nlog_errors = On\n" .
                'error_log = "' . $folder . '/' . self::LOG . "\"\n"
        );
        // An empty entry makes PHP scan its own folder too, which it does
        // when PHP_INI_SCAN_DIR is not set.
        if ($scan === false) {
            $scan = PATH_SEPARATOR . $folder;
        } elseif ($scan === '') {
            $scan = $folder;
        } else {
            $scan .= PATH_SEPARATOR . $folder;
        }
        putenv('PHP_INI_SCAN_DIR=' . $scan);
        self::logHere();

        register_shutdown_function([self::class, 'check']);
    }

    /**
     * The folder to add to a PHP_INI_SCAN_DIR a test sets itself.
     */
    public static function folder(): string
    {
        return self::$folder;
    }

    /**
     * The command line options that give a PHP started with -n the
     * settings of the ini file.
     *
     * @return array<string>
     */
    public static function settings(): array
    {
        return [
            '-d', 'error_reporting=-1',
            '-d', 'log_errors=On',
            '-d', 'error_log=' . self::$folder . '/' . self::LOG,
        ];
    }

    /**
     * Takes out of the log each message that matches the pattern, with the
     * lines that follow it such as a stack trace, so that a test that makes
     * a PHP fail on purpose does not fail the run. Returns how many it took,
     * which the test checks.
     */
    public static function take(string $pattern): int
    {
        $log = self::$folder . '/' . self::LOG;
        if (!is_file($log)) {
            return 0;
        }
        $kept = [];
        $taken = 0;
        $taking = false;
        foreach ((array) file($log) as $line) {
            // Each message starts with the time in square brackets.
            if (preg_match('~^\[[^]]*\] ~', $line) === 1) {
                $taking = preg_match($pattern, $line) === 1;
                $taken += $taking ? 1 : 0;
            }
            if (!$taking) {
                $kept[] = $line;
            }
        }
        file_put_contents($log, implode('', $kept));
        return $taken;
    }

    /**
     * Prints the log, removes the folder, and ends the run with a failing
     * status when the log holds a warning, notice, deprecation or fatal
     * error.
     */
    public static function check(): void
    {
        // A message raised from here on goes to standard error, as the log
        // is removed.
        ini_set('error_log', '');
        $log = self::$folder . '/' . self::LOG;
        $lines = is_file($log)
            ? (array) file($log, FILE_IGNORE_NEW_LINES)
            : [];
        foreach ([$log, self::$folder . '/' . self::INI] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir(self::$folder);

        if ($lines === []) {
            return;
        }
        fwrite(
            STDERR,
            "\nPHP started by the tests logged:\n" . implode("\n", $lines) . "\n"
        );
        if (preg_grep(self::FAILS, $lines) !== []) {
            fwrite(
                STDERR,
                'The run fails, as a warning, notice, deprecation or fatal ' .
                    "error is an error.\n"
            );
            exit(1);
        }
    }

    /**
     * Logs the messages of this process to the same file. A message that
     * PHPUnit's error handler takes is not logged, and one it does not take
     * is checked with those of the PHPs the tests start.
     */
    private static function logHere(): void
    {
        ini_set('log_errors', '1');
        ini_set('error_log', self::$folder . '/' . self::LOG);
    }
}

/**
 * Runs a call that is expected to fail at times with a message of one
 * level, such as a connection tried before a server listens, or the
 * removal of a file that may be gone. A message of that level from the
 * call is taken and handed back, not printed. A message of any other
 * level is not taken. PHP reports it, and the check above fails the run.
 */
final class PhpMessage
{
    /**
     * Returns what the call returns, and sets $message to the last message
     * of the level that the call raised, or null.
     *
     * @return mixed
     */
    public static function take(
        callable $call,
        ?string &$message = null,
        int $level = E_WARNING
    ) {
        $message = null;
        set_error_handler(
            static function (int $raised, string $text) use (&$message): bool {
                $message = $text;
                return true;
            },
            $level
        );
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }
}

ChildPhp::watch();

// The package's own vendor folder, or the root one of this repository.
$folder = dirname(__DIR__);
while (!is_file($folder . '/vendor/autoload.php') &&
    dirname($folder) !== $folder) {
    $folder = dirname($folder);
}
require $folder . '/vendor/autoload.php';
