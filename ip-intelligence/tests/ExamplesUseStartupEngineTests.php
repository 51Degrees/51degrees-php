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

namespace fiftyone\pipeline\ipintelligence\tests;

use PHPUnit\Framework\TestCase;

/**
 * Every on-premise example uses the engine the extension builds once, when
 * PHP starts, from the FiftyOneDegreesIpIntelligenceEngine.data_file php.ini
 * setting. An example that passes the "dataFile" setting, or builds the
 * native engine itself, builds a new engine from the data file every time
 * it runs, which under a web server is every request, and teaches that to
 * everyone who copies it.
 */
class ExamplesUseStartupEngineTests extends TestCase
{
    private const EXAMPLES = __DIR__ . '/../examples';

    public function testNoExampleBuildsAnEngineFromADataFile(): void
    {
        $found = [];
        $read = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                self::EXAMPLES,
                \FilesystemIterator::SKIP_DOTS
            )
        );
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            ++$read;
            $name = substr($file->getPathname(), strlen(self::EXAMPLES) + 1);
            foreach (file($file->getPathname()) as $index => $line) {
                if (preg_match('/\bdataFile\b|EngineIpiSwig/', $line)) {
                    $found[] = sprintf(
                        '%s line %d: %s',
                        str_replace('\\', '/', $name),
                        $index + 1,
                        trim($line)
                    );
                }
            }
        }
        sort($found);

        $this->assertGreaterThan(20, $read, 'The examples were not found.');
        $this->assertSame(
            [],
            $found,
            'These examples build an engine from a data file rather than ' .
            'using the one built when PHP starts.'
        );
    }
}
