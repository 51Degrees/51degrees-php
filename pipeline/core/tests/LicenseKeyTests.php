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

use fiftyone\pipeline\core\LicenseKey;
use PHPUnit\Framework\TestCase;

/**
 * A license key shows only its last 4 characters, and a key of 4 characters
 * or fewer is replaced entirely.
 */
class LicenseKeyTests extends TestCase
{
    public function testMaskKeepsTheLastFourCharacters()
    {
        $this->assertSame('*********1234', LicenseKey::mask('SECRETKEY1234'));
    }

    public function testMaskReplacesAShortKeyEntirely()
    {
        $this->assertSame('****', LicenseKey::mask('abcd'));
        $this->assertSame('***', LicenseKey::mask('abc'));
        $this->assertSame('', LicenseKey::mask(''));
    }

    public function testMaskQueryMasksTheKeyOfAUrl()
    {
        $this->assertSame(
            'https://example.com/a.json?x=1&license=*********1234&y=2',
            LicenseKey::maskQuery('https://example.com/a.json?x=1&license=SECRETKEY1234&y=2')
        );
    }

    public function testMaskQueryMasksEveryKeyOfAList()
    {
        $this->assertSame(
            'https://example.com/d?LicenseKeys=*******1111|*******2222&Type=x',
            LicenseKey::maskQuery('https://example.com/d?LicenseKeys=KEYSONE1111|KEYSTWO2222&Type=x')
        );
    }

    public function testMaskQueryMasksTheKeyInsideAMessage()
    {
        $this->assertSame(
            "The request to 'http://h/p?licenseKey=*********1234' failed: x",
            LicenseKey::maskQuery("The request to 'http://h/p?licenseKey=SECRETKEY1234' failed: x")
        );
    }

    public function testMaskQueryLeavesOtherParametersAlone()
    {
        $text = 'http://h/p?resource=ABCDEFGH&licensed=no&query=license';
        $this->assertSame($text, LicenseKey::maskQuery($text));
    }
}
