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
 * Every Device Detection package shares the prefix of its Composer name.
 * Each package directory under device-detection is read, so a package added
 * there is checked too.
 */
class PackagingTests extends TestCase
{
    private const PREFIX = '51degrees/fiftyone.devicedetection';

    private const PACKAGES = __DIR__ . '/../../*/composer.json';

    /**
     * The cloud and on-premise packages.
     */
    private const MIN_PACKAGES = 2;

    public function testEveryPackageNameHasTheCommonPrefix(): void
    {
        $files = glob(self::PACKAGES);
        $this->assertGreaterThanOrEqual(self::MIN_PACKAGES, count($files));

        foreach ($files as $file) {
            $composer = json_decode((string) file_get_contents($file), true);
            $this->assertIsArray($composer, $file);
            $this->assertStringStartsWith(self::PREFIX, $composer['name'] ?? '', $file);
        }
    }
}
