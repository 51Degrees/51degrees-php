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

namespace fiftyone\pipeline\devicedetection\tests;

use PHPUnit\Framework\TestCase;

/**
 * Composer installs this package from the archive GitHub makes of the
 * package's mirror, which leaves out each path the package's .gitattributes
 * marks export-ignore. The mirror holds this folder at its root, so the
 * archive of this folder is the archive Composer installs.
 */
class PublishedPackageTests extends TestCase
{
    // The examples and tests, which the published package leaves out.
    private const LEFT_OUT = ['examples', 'tests', 'phpunit.xml'];

    public function testThePublishedPackageHasNoExamplesOrTests(): void
    {
        $names = $this->publishedNames();

        $this->assertContains('composer.json', $names);
        $this->assertContains('src/HardwareProfileCloud.php', $names);

        $found = array_values(array_filter(
            $names,
            static function (string $name): bool {
                foreach (self::LEFT_OUT as $path) {
                    if ($name === $path || strpos($name, $path . '/') === 0) {
                        return true;
                    }
                }

                return false;
            }
        ));
        $this->assertSame([], $found, 'The published package holds these.');
    }

    /**
     * Every path in git's archive of this package's folder at HEAD. The
     * archive is made from the top of the checkout, because git archive run
     * in a folder keeps only the paths under that folder.
     *
     * @return array<string>
     */
    private function publishedNames(): array
    {
        $package = dirname(__DIR__);
        exec(
            'git -C ' . escapeshellarg($package) .
                ' rev-parse --show-toplevel --show-prefix 2>&1',
            $lines,
            $status
        );
        if ($status !== 0) {
            $this->markTestSkipped(
                'The package is not in a git checkout: ' . implode(' ', $lines)
            );
        }
        $top = trim($lines[0] ?? '');
        $prefix = trim($lines[1] ?? '');

        $file = sys_get_temp_dir() . '/51d-package-' . uniqid() . '.tar';
        $output = [];
        exec(
            'git -C ' . escapeshellarg($top) . ' archive --format=tar' .
                ' -o ' . escapeshellarg($file) . ' ' .
                escapeshellarg('HEAD:' . $prefix) . ' 2>&1',
            $output,
            $status
        );
        $this->assertSame(0, $status, implode("\n", $output));

        $names = [];
        try {
            $entries = new \RecursiveIteratorIterator(
                new \PharData($file),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($entries as $entry) {
                $path = str_replace('\\', '/', $entry->getPathname());
                $names[] = substr($path, strpos($path, '.tar/') + 5);
            }
        } finally {
            unset($entries);
            @unlink($file);
        }
        sort($names);

        return $names;
    }
}
