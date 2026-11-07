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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\JsonBundlerElement;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\SetHeaderElement;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement1;
use PHPUnit\Framework\TestCase;

/**
 * An element accepts only the Evidence that can change its results, so the
 * Evidence a Pipeline keeps, and anything keyed on it, never holds a header
 * that no element uses, such as If-None-Match.
 */
class AcceptedEvidenceTests extends TestCase
{
    private const UNUSED_KEYS = [
        'header.user-agent',
        'header.if-none-match',
        'header.x-test',
        'cookie.51d_test',
        'query.x-test',
        'server.client-ip'
    ];

    /**
     * The Set Header element reads only the element data of the elements
     * before it.
     */
    public function testSetHeaderElementAcceptsNoEvidence()
    {
        $element = new SetHeaderElement();

        foreach (self::UNUSED_KEYS as $key) {
            $this->assertFalse(
                $element->filterEvidenceKey($key),
                "The Set Header element accepts {$key}"
            );
        }
    }

    /**
     * The JSON bundler reads only the sequence, which decides whether it
     * lists the JavaScript properties.
     */
    public function testJsonBundlerAcceptsOnlyTheSequence()
    {
        $element = new JsonBundlerElement();

        $this->assertTrue($element->filterEvidenceKey('query.sequence'));
        foreach (self::UNUSED_KEYS as $key) {
            $this->assertFalse(
                $element->filterEvidenceKey($key),
                "The JSON bundler accepts {$key}"
            );
        }
    }

    /**
     * A web Pipeline, which adds the Sequence, JSON bundler, JavaScript
     * builder and Set Header elements, keeps only the Evidence one of its
     * elements accepts.
     */
    public function testAWebPipelineKeepsOnlyEvidenceAnElementAccepts()
    {
        $pipeline = (new PipelineBuilder())
            ->add(new ExampleFlowElement1())
            ->build();
        $flowData = $pipeline->createFlowData();

        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->evidence->set('query.sequence', '1');
        $flowData->evidence->set('header.host', 'example.com');
        $flowData->evidence->set('header.if-none-match', '"etag"');
        $flowData->evidence->set('header.x-test', 'not used');
        $flowData->evidence->set('cookie.51d_test', 'not used');

        $this->assertSame(
            [
                'header.user-agent' => 'agent',
                'query.sequence' => '1',
                'header.host' => 'example.com'
            ],
            $flowData->evidence->getAll()
        );
    }
}
