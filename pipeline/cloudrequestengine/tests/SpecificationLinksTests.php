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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestData;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\CloudRequestFailedData;
use fiftyone\pipeline\cloudrequestengine\DerivedCloudEngine;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\cloudrequestengine\HumanConfidencePage;
use fiftyone\pipeline\cloudrequestengine\HumanConfidenceTransactions;
use fiftyone\pipeline\cloudrequestengine\SealedHumanConfidence;
use fiftyone\pipeline\cloudrequestengine\SealedResultFailedData;
use PHPUnit\Framework\TestCase;

/**
 * Each class that implements a part of the Pipeline specification cites the
 * URL of that part in its class comment, so that the comment points to the
 * description rather than repeating it.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/README.md
 */
class SpecificationLinksTests extends TestCase
{
    private const SPECIFICATION =
        'https://github.com/51Degrees/specifications/blob/main/pipeline-specification/';

    /**
     * Each class, with the part of the specification it implements.
     */
    private const LINKS = [
        CloudEngine::class => 'pipeline-elements/cloud-aspect-engine.md',
        CloudRequestData::class => 'pipeline-elements/cloud-request-engine.md#element-data',
        CloudRequestEngine::class => 'pipeline-elements/cloud-request-engine.md',
        CloudRequestException::class => 'features/exception-handling.md',
        CloudRequestFailedData::class => 'features/properties.md#missing-properties',
        HttpClient::class => 'pipeline-elements/cloud-request-engine.md#http-requests',
        HumanConfidencePage::class => 'features/human-confidence.md#the-transaction',
        HumanConfidenceTransactions::class => 'features/human-confidence.md#the-transaction',
        DerivedCloudEngine::class => 'features/human-confidence.md#when-humanconfidence-arrives-sealed',
        SealedHumanConfidence::class => 'features/human-confidence.md#when-humanconfidence-arrives-sealed',
        SealedResultFailedData::class => 'features/human-confidence.md#where-the-sealed-result-is-not-redeemed',
    ];

    public function testEachClassCitesItsPartOfTheSpecification(): void
    {
        $missing = [];
        foreach (self::LINKS as $class => $part) {
            $comment = (string) (new \ReflectionClass($class))->getDocComment();
            if (strpos($comment, self::SPECIFICATION . $part) === false) {
                $missing[] = $class . ' does not cite ' . self::SPECIFICATION . $part;
            }
        }

        $this->assertSame([], $missing);
    }
}
