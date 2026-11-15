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
 * Logging for a Pipeline. Given a location in the file setting, the Logger
 * adds each entry at or above its level to that location as a line of
 * text. Without one it writes nothing, and a subclass can override
 * logInternal to send entries elsewhere.
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
     * The PHP error_log message type that adds the message to a location.
     */
    private const ERROR_LOG_APPEND = 3;

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
     *   Defaults to 'error'.
     * @param array<string, mixed> $settings Custom settings for a logger,
     *   including FILE_SETTING, the location entries are added to, which
     *   has no default
     */
    public function __construct(?string $level, array $settings = [])
    {
        $level = strtolower((string) $level);

        if (!in_array($level, $this->levels)) {
            $level = 'error';
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
     * time, level and message, or does nothing without one. Writing never
     * throws, so a location that cannot be written does not stop the
     * request being processed. Overridden by loggers that send entries
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

        // An application's error handler can turn the warning of a failed
        // write into an exception, which is dropped with the entry.
        try {
            @error_log(
                sprintf("%s %s %s\n", $log['time'], $log['level'], $log['message']),
                self::ERROR_LOG_APPEND,
                $file
            );
        } catch (\Throwable $e) {
            return;
        }
    }
}
