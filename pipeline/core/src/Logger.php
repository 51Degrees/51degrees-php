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

declare(strict_types=1);

namespace fiftyone\pipeline\core;

/**
 * Logging for a Pipeline.
 *
 * With a 'file' setting, the logger appends each entry at or above its level
 * to the file, or stream such as php://stderr, that the setting names, one
 * line for each entry: the time, the level in brackets and the message.
 * With none it writes nothing, unless a subclass overrides logInternal to
 * send the entries elsewhere.
 *
 * The level and the file can be set without changing code, in the
 * PipelineOptions.Logging section of the configuration that
 * PipelineBuilder::buildFromConfig reads:
 *
 *     "Logging": { "Level": "Warning", "File": "/var/log/pipeline.log" }
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/logging.md
 */
class Logger
{
    /**
     * The setting that gives the location entries are added to, being a
     * file path or a PHP stream such as php://stderr.
     */
    public const FILE_SETTING = 'file';

    /**
     * The level used when none, or one the Logger does not know, is given.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/reference-implementation-notes.md#builders
     */
    public const DEFAULT_LEVEL = 'error';

    /**
     * @var array<string, mixed>
     */
    public array $settings;

    private int $minLevel;

    /**
     * @var array<string>
     */
    private array $levels = ['trace', 'debug', 'info', 'warning', 'error', 'critical'];

    /**
     * Create a logger.
     *
     * @param null|string $level Values: 'trace', 'debug', 'info', 'warning', 'error', 'critical'.
     *   Defaults to DEFAULT_LEVEL.
     * @param array<string, mixed> $settings Custom settings for a logger,
     *   including FILE_SETTING, the location entries are added to, which
     *   has no default
     */
    public function __construct(?string $level, array $settings = [])
    {
        $level = strtolower((string) $level);

        if (!in_array($level, $this->levels)) {
            $level = self::DEFAULT_LEVEL;
        }

        $this->settings = $settings;
        $this->minLevel = array_search($level, $this->levels);
    }

    /**
     * Log a message.
     */
    public function log(string $level, string $message): void
    {
        $levelIndex = array_search(strtolower($level), $this->levels);

        if ($levelIndex >= $this->minLevel) {
            $log = [
                'time' => date('Y-m-d H:i:s'),
                'level' => $level,
                'message' => $message
            ];

            $this->logInternal($log);
        }
    }

    /**
     * Adds the entry to the location in the file setting as one line of
     * time, level in brackets and message, or does nothing without one.
     * Writing never throws, so a location that cannot be written does not
     * stop the request being processed, and the entry goes where PHP logs
     * its own errors instead. Overridden by loggers that send entries
     * elsewhere.
     *
     * @param array<string, string> $log
     */
    public function logInternal(array $log): void
    {
        $file = $this->settings[self::FILE_SETTING] ?? null;
        if (!is_string($file) || $file === '') {
            return;
        }

        $line = sprintf("%s [%s] %s\n", $log['time'], $log['level'], $log['message']);

        // Only a file on disk can be locked, not a stream such as
        // php://stderr.
        $flags = FILE_APPEND;
        if (strpos($file, '://') === false) {
            $flags |= LOCK_EX;
        }

        // A failed write gives a warning and false. The warning is taken
        // as the reason, so that an application's error handler does not
        // stop the request over a log entry.
        $reason = null;
        set_error_handler(
            static function (int $level, string $message) use (&$reason): bool {
                $reason = $message;

                return true;
            },
            E_WARNING
        );

        try {
            $written = file_put_contents($file, $line, $flags);
        } catch (\Throwable $e) {
            // Such as the error PHP 8 throws for a path it cannot take.
            $written = false;
            $reason = $e->getMessage();
        } finally {
            restore_error_handler();
        }

        if ($written === false) {
            // The log cannot be written, so say so, and why, where PHP logs
            // its own errors rather than stopping the request.
            error_log(sprintf(Messages::LOG_FILE_NOT_WRITTEN, $file) . ' ' . rtrim($line) .
                ($reason === null ? '' : ' (' . $reason . ')'));
        } elseif ($reason !== null) {
            // A warning from a write that was made is not expected, so it
            // is raised again.
            trigger_error($reason, E_USER_WARNING);
        }
    }
}
