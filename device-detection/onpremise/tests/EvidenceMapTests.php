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

namespace fiftyone\pipeline\devicedetection\tests;

use PHPUnit\Framework\TestCase;

/**
 * The map methods of the extension's evidence class. They are defined on
 * EvidenceDeviceDetectionSwig itself (see hash-php.i), because the ones
 * inherited from the map class work on the wrong memory.
 */
class EvidenceMapTests extends TestCase
{
    public function testNewEvidenceIsEmpty()
    {
        $evidence = new \EvidenceDeviceDetectionSwig();

        $this->assertSame(0, $evidence->size());
        $this->assertTrue($evidence->is_empty());
        $this->assertFalse($evidence->has_key('header.user-agent'));
    }

    public function testSetThenGetHasKeyAndSize()
    {
        $evidence = new \EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', 'one');
        $evidence->set('header.sec-ch-ua', 'two');

        $this->assertSame(2, $evidence->size());
        $this->assertFalse($evidence->is_empty());
        $this->assertTrue($evidence->has_key('header.user-agent'));
        $this->assertSame('one', $evidence->get('header.user-agent'));
        $this->assertSame('two', $evidence->get('header.sec-ch-ua'));
    }

    public function testSetReplacesAnExistingValue()
    {
        $evidence = new \EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', 'one');
        $evidence->set('header.user-agent', 'two');

        $this->assertSame(1, $evidence->size());
        $this->assertSame('two', $evidence->get('header.user-agent'));
    }

    public function testDelRemovesOnlyThatKey()
    {
        $evidence = new \EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', 'one');
        $evidence->set('header.sec-ch-ua', 'two');
        $evidence->del('header.user-agent');

        $this->assertSame(1, $evidence->size());
        $this->assertFalse($evidence->has_key('header.user-agent'));
        $this->assertTrue($evidence->has_key('header.sec-ch-ua'));
    }

    public function testClearEmptiesTheEvidence()
    {
        $evidence = new \EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', 'one');
        $evidence->clear();

        $this->assertSame(0, $evidence->size());
        $this->assertTrue($evidence->is_empty());
    }

    public function testGetOfAMissingKeyThrows()
    {
        $evidence = new \EvidenceDeviceDetectionSwig();

        $this->expectException(\Exception::class);
        $evidence->get('header.user-agent');
    }

    public function testDelOfAMissingKeyThrows()
    {
        $evidence = new \EvidenceDeviceDetectionSwig();

        $this->expectException(\Exception::class);
        $evidence->del('header.user-agent');
    }
}
