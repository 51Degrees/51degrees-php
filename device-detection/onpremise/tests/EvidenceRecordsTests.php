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

use fiftyone\pipeline\devicedetection\tests\classes\EvidenceRecords;
use PHPUnit\Framework\TestCase;

/**
 * The YAML evidence file is read as the records it holds, each with its
 * evidence keys and values, as 20000 Evidence Records.yml writes them.
 */
class EvidenceRecordsTests extends TestCase
{
    /** @var string */
    private $file = '';

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'evidence');
        file_put_contents($this->file, implode("\n", [
            '---',
            'header.user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/155.0.0.0',
            '---',
            '{}',
            '---',
            "header.sec-ch-ua-platform: '\"macOS\"'",
            'header.sec-ch-ua-mobile: ?0',
            "header.user-agent: 'Mozilla/5.0 (compatible; id: it''s quoted)'",
            '---',
            'header.sec-ch-ua: "\"Chromium\";v=\"142\""',
            '...',
            ''
        ]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testEachRecordIsReadWithItsEvidence(): void
    {
        $this->assertSame([
            ['header.user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/155.0.0.0'],
            [
                'header.sec-ch-ua-platform' => '"macOS"',
                'header.sec-ch-ua-mobile' => '?0',
                'header.user-agent' => "Mozilla/5.0 (compatible; id: it's quoted)"
            ],
            ['header.sec-ch-ua' => '"Chromium";v="142"']
        ], EvidenceRecords::read($this->file));
    }

    public function testTheValuesOfOneKeyAreReadUpToTheLimit(): void
    {
        $this->assertSame(
            ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/155.0.0.0'],
            EvidenceRecords::values($this->file, 'header.user-agent', 1)
        );
        $this->assertCount(
            2,
            EvidenceRecords::values($this->file, 'header.user-agent', 10)
        );
    }

    public function testAFileThatIsNotThereHoldsNoRecords(): void
    {
        $this->assertSame(
            [],
            EvidenceRecords::read(sys_get_temp_dir() . '/no-such-evidence.yml')
        );
    }
}
