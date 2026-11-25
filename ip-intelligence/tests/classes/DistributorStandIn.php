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

namespace fiftyone\pipeline\ipintelligence\tests\classes;

/**
 * A stand-in for the distributor's free data file link on this machine, so
 * that a test of a download never reaches the distributor. It is PHP's
 * built-in web server with distributor-stand-in.php as its router, and
 * answers as the distributor does for a free data file:
 * - a GET for the link's path gets the data file, gzip-compressed, with the
 *   MD5 hash of the compressed bytes as lower case hexadecimal in
 *   Content-MD5 and the file's published date in Last-Modified
 * - a GET with an If-Modified-Since that is not earlier than the published
 *   date gets 304 (Not modified)
 * - anything else gets 404 (Not found)
 * Every request is recorded, so a test can check what was asked for.
 */
class DistributorStandIn
{
    /**
     * Where the published date starts in the header of a Hash or an IP
     * Intelligence data file. The date is a 16 bit year, little endian,
     * then a byte each for the month and the day.
     */
    private const PUBLISHED_OFFSET = 66;

    // The file in the server's folder that tells the router what to serve.
    private const SETTINGS = 'stand-in.json';

    // How long the server has to start.
    private const START_SECONDS = 30;

    /** @var string The folder holding the compressed file and the logs */
    private $folder;

    /** @var string Where PHP's built-in web server writes what it logs */
    private $serverLog;

    /** @var string Where the router records each request */
    private $requestLog;

    /** @var string The host and port the server listens on */
    private $address;

    /** @var string The path the data file is served at */
    private $path;

    /** @var int The data file's published date, as a Unix time */
    private $published;

    /** @var resource|null The server's process */
    private $server;

    /**
     * Starts serving the data file at the path.
     *
     * @param string $dataFile The data file to serve
     * @param string $path The path of the link the file is served at
     */
    public function __construct(string $dataFile, string $path)
    {
        $this->path = $path;
        $this->published = self::readPublished($dataFile);
        $this->folder = sys_get_temp_dir() . '/fiftyone-distributor-' .
            getmypid() . '-' . uniqid();
        if (!mkdir($this->folder, 0777, true)) {
            throw new \RuntimeException("{$this->folder} could not be made.");
        }
        $this->serverLog = $this->folder . '/server.log';
        $this->requestLog = $this->folder . '/requests.log';
        $compressed = $this->folder . '/' . basename($dataFile) . '.gz';
        self::compress($dataFile, $compressed);

        // A free port, from a socket the system gives one to and then closes.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        if ($socket === false) {
            throw new \RuntimeException("No free port: {$message}");
        }
        $this->address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        // The router reads what to serve from the folder the server starts
        // in, which is its document root.
        file_put_contents(
            $this->folder . '/' . self::SETTINGS,
            json_encode([
                'path' => $path,
                'file' => $compressed,
                'md5' => (string) md5_file($compressed),
                'published' => $this->published,
                'log' => $this->requestLog,
            ], JSON_THROW_ON_ERROR)
        );

        // -n, as the router needs nothing from php.ini and no extension.
        $server = proc_open(
            [
                PHP_BINARY,
                '-n',
                '-S',
                $this->address,
                __DIR__ . '/distributor-stand-in.php',
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->serverLog, 'a'],
                2 => ['file', $this->serverLog, 'a'],
            ],
            $pipes,
            $this->folder
        );
        if (!is_resource($server)) {
            throw new \RuntimeException('PHP\'s built-in web server did not start.');
        }
        fclose($pipes[0]);
        $this->server = $server;
        $this->waitForStart();
    }

    /**
     * The address of the data file, to give the download in place of the
     * distributor's.
     */
    public function url(): string
    {
        return "http://{$this->address}{$this->path}";
    }

    /**
     * The data file's published date, as a Unix time, which the stand-in
     * sends as Last-Modified.
     */
    public function published(): int
    {
        return $this->published;
    }

    /**
     * Every request received so far, in the order received, each with its
     * method, path, If-Modified-Since, or null, and the status answered.
     *
     * @return array<array{method: string, path: string, ifModifiedSince: ?string, status: int}>
     */
    public function requests(): array
    {
        if (!is_file($this->requestLog)) {
            return [];
        }
        $requests = [];
        foreach (file($this->requestLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $requests[] = json_decode($line, true, 4, JSON_THROW_ON_ERROR);
        }

        return $requests;
    }

    /**
     * What the server logged, for a failure message.
     */
    public function log(): string
    {
        return is_file($this->serverLog)
            ? (string) file_get_contents($this->serverLog)
            : '';
    }

    /**
     * Stops the server and deletes its folder.
     */
    public function stop(): void
    {
        if ($this->server !== null) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        if (is_dir($this->folder)) {
            foreach (scandir($this->folder) ?: [] as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink("{$this->folder}/{$file}");
                }
            }
            rmdir($this->folder);
        }
    }

    /**
     * The published date in a data file's header, as a Unix time.
     */
    public static function readPublished(string $dataFile): int
    {
        $file = fopen($dataFile, 'rb');
        if ($file === false) {
            throw new \RuntimeException("{$dataFile} could not be opened.");
        }
        try {
            fseek($file, self::PUBLISHED_OFFSET);
            $bytes = (string) fread($file, 4);
        } finally {
            fclose($file);
        }
        if (strlen($bytes) !== 4) {
            throw new \RuntimeException("{$dataFile} is too short to be a data file.");
        }
        $date = unpack('vyear/Cmonth/Cday', $bytes);

        return (int) gmmktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
    }

    /**
     * Writes the gzip-compressed data file, as the distributor sends it.
     */
    private static function compress(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new \RuntimeException("{$source} could not be opened.");
        }
        $out = gzopen($target, 'wb6');
        if ($out === false) {
            fclose($in);

            throw new \RuntimeException("{$target} could not be written.");
        }
        try {
            while (!feof($in)) {
                $chunk = (string) fread($in, 1048576);
                if ($chunk !== '' && gzwrite($out, $chunk) !== strlen($chunk)) {
                    throw new \RuntimeException("{$target} could not be written.");
                }
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    /**
     * Returns once the server logs that it has started, and throws when it
     * stops or does not start in time.
     */
    private function waitForStart(): void
    {
        // As in "Development Server (http://127.0.0.1:8080) started".
        $started = "://{$this->address}) started";
        $deadline = microtime(true) + self::START_SECONDS;
        while (microtime(true) < $deadline) {
            clearstatcache(true, $this->serverLog);
            if (strpos($this->log(), $started) !== false) {
                return;
            }
            if (!proc_get_status($this->server)['running']) {
                break;
            }
            usleep(100000);
        }
        $log = $this->log();
        $this->stop();

        throw new \RuntimeException(
            "PHP's built-in web server did not start on {$this->address}.\n{$log}"
        );
    }
}
