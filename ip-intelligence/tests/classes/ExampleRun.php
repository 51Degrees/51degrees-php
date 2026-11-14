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

namespace fiftyone\pipeline\ipintelligence\tests\classes;

use PHPUnit\Framework\Assert;

/**
 * An example run in its own PHP, as a person runs it, with what it printed
 * and its exit status, and the checks every example's output must pass.
 */
class ExampleRun
{
    // The heading of the section a console example ends with.
    public const HEADING = "Find out more\n-------------\n";

    // The environment variables a resource key is read from.
    public const RESOURCE_KEY_VARIABLES = ['_51DEGREES_RESOURCE_KEY', 'resource_key'];

    // The variables a web server gives a script for a request for the home
    // page. PHP from the command line puts environment variables in
    // $_SERVER, so a page run this way sees the request.
    public const WEB_REQUEST = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_HOST' => 'localhost',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
            'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];

    // A link printed by an example of this repository, tagged with the
    // example's file as utm_content, or by a package of this repository
    // in a message the example prints, tagged with the source file. Either
    // may end with a place on the page.
    private const TAGGED = '~^https://51degrees\.com/[^?#]*\?utm_source=code' .
        '&utm_medium=(?:example&utm_campaign=51degrees-php&utm_content=examples-' .
        '|comment&utm_campaign=51degrees-php&utm_content=src-)' .
        '[a-z0-9._-]*&utm_term=[a-z0-9][a-z0-9._-]{0,59}(#[A-Za-z0-9_-]+)?$~';

    // A link tagged with another repository's name, which the engine's
    // own messages carry, such as the reason a property has no value.
    private const OTHER_REPOSITORY = '~[?&]utm_campaign=(?!51degrees-php&)[a-z0-9._-]+&~';

    // The hosts of 51degrees.com links that must carry the tags. Others,
    // such as the cloud service, are addresses a program calls.
    private const TAGGED_HOSTS = ['51degrees.com', 'www.51degrees.com', 'configure.51degrees.com'];

    /** @var string What the example wrote to standard output */
    public $output = '';

    /** @var string What the example wrote to standard error */
    public $errors = '';

    /** @var int The example's exit status */
    public $status = -1;

    /**
     * Runs the script in a new PHP from the script's folder, with the
     * environment of the tests less the variables named in $unset and with
     * those in $set added.
     *
     * @param string $script The example's file
     * @param array<string> $php Arguments for PHP before the script
     * @param array<string> $unset Environment variables to leave out
     * @param array<string, string> $set Environment variables to add
     * @param array<string> $args Arguments for the script
     */
    public static function run(
        string $script,
        array $php = [],
        array $unset = [],
        array $set = [],
        array $args = []
    ): self {
        $environment = getenv();
        foreach ($unset as $name) {
            unset($environment[$name]);
        }
        $environment = array_merge($environment, $set);
        $errors = tempnam(sys_get_temp_dir(), 'example');
        $process = proc_open(
            array_merge([PHP_BINARY], $php, [$script], $args),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']],
            $pipes,
            dirname($script),
            $environment
        );
        Assert::assertIsResource($process, "{$script} could not be started.");
        fclose($pipes[0]);
        $run = new self();
        $run->output = str_replace("\r\n", "\n", (string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $run->status = proc_close($process);
        $run->errors = str_replace("\r\n", "\n", (string) file_get_contents($errors));
        unlink($errors);

        return $run;
    }

    /**
     * Checks that the output ends with the Find out more section, being its
     * heading followed only by lines that give a label and an address, and
     * that every 51degrees.com link printed is tagged.
     */
    public function assertEndsWithFindOutMore(): void
    {
        $at = strrpos($this->output, self::HEADING);
        Assert::assertNotFalse($at, "No Find out more section.\n{$this->printed()}");
        $lines = array_filter(
            explode("\n", substr($this->output, $at + strlen(self::HEADING))),
            function ($line) {
                return trim($line) !== '';
            }
        );
        Assert::assertNotEmpty($lines, "Find out more lists nothing.\n{$this->printed()}");
        foreach ($lines as $line) {
            Assert::assertMatchesRegularExpression(
                '~^[^:]+: https://\S+$~',
                $line,
                "Something other than a link follows Find out more.\n{$this->printed()}"
            );
        }
        $this->assertLinksTagged();
    }

    /**
     * Checks that the page shows the Find out more section with nothing
     * visible after it, and that every 51degrees.com link is tagged.
     */
    public function assertPageEndsWithFindOutMore(): void
    {
        $at = strrpos($this->output, '<div id="find-out-more">');
        Assert::assertNotFalse($at, "No Find out more section.\n{$this->printed()}");
        $end = strpos($this->output, '</ul>', $at);
        Assert::assertNotFalse($end, "Find out more has no list.\n{$this->printed()}");
        $after = preg_replace(
            '~<script\b.*?</script>|<style\b.*?</style>|<!--.*?-->~s',
            '',
            substr($this->output, $end)
        );
        Assert::assertSame(
            '',
            trim(html_entity_decode(strip_tags((string) $after))),
            "Something is shown after Find out more.\n{$this->printed()}"
        );
        $this->assertLinksTagged();
    }

    /**
     * Checks that every 51degrees.com link the example printed, to either
     * stream, carries the five tags in order. A link from the engine,
     * tagged by its own repository, is that repository's to check.
     */
    public function assertLinksTagged(): void
    {
        preg_match_all(
            '~https?://([a-z0-9.-]*51degrees\.com)[^\s"\'<>()\[\]{},`\\\\]*~i',
            html_entity_decode($this->output . "\n" . $this->errors),
            $links,
            PREG_SET_ORDER
        );
        foreach ($links as $link) {
            if (!in_array(strtolower($link[1]), self::TAGGED_HOSTS, true) ||
                preg_match(self::OTHER_REPOSITORY, $link[0]) === 1) {
                continue;
            }
            Assert::assertMatchesRegularExpression(
                self::TAGGED,
                rtrim($link[0], '.,;:!?'),
                "A printed link is not tagged.\n{$this->printed()}"
            );
        }
    }

    /**
     * What the example printed, for a failure message.
     */
    public function printed(): string
    {
        return "Exit status {$this->status}.\nStandard output:\n{$this->output}\n" .
            "Standard error:\n{$this->errors}";
    }
}
