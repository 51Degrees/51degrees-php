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

namespace fiftyone\pipeline\core\tests;

use PHPUnit\Framework\TestCase;

/**
 * Every web example, and the documentation of the web integration, says
 * that a Flow Data cannot be used by two threads at once, and the examples
 * show it by creating a Flow Data for each request.
 */
class FlowDataThreadsDocsTests extends TestCase
{
    private const SENTENCE = 'must not be used by two threads at once';

    public function testTheReadmeSaysAFlowDataIsUsedByOneThread(): void
    {
        $readme = (string) file_get_contents(dirname(__DIR__) . '/readme.md');

        $this->assertStringContainsString('## Flow Data and threads', $readme);
        $this->assertStringContainsString(self::SENTENCE, $readme);
    }

    public function testEveryWebExampleSaysAndShowsItsFlowDataIsForOneRequest(): void
    {
        $examples = self::webExamples(dirname(__DIR__, 3));

        $this->assertGreaterThan(5, count($examples));
        foreach ($examples as $example) {
            $text = (string) file_get_contents($example);
            $this->assertStringContainsString(self::SENTENCE, $text, $example);
            $this->assertMatchesRegularExpression(
                '/' . preg_quote(self::SENTENCE, '/') .
                '.*\n(\s*\/\/.*\n)*\s*\$\w+\s*=\s*\$\w+->createFlowData\(\);/',
                $text,
                $example
            );
        }
    }

    /**
     * Every example that reads a web request and creates a Flow Data.
     *
     * @return array<int, string>
     */
    private static function webExamples(string $root): array
    {
        $found = [];
        foreach (['pipeline', 'device-detection', 'ip-intelligence', 'location'] as $top) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $top, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if (substr($path, -4) !== '.php' ||
                    strpos($path, '/examples/') === false ||
                    strpos($path, '/vendor/') !== false) {
                    continue;
                }
                $text = (string) file_get_contents($path);
                if (strpos($text, 'setFromWebRequest(') !== false &&
                    strpos($text, 'createFlowData()') !== false) {
                    $found[] = $path;
                }
            }
        }

        return $found;
    }
}
