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

use fiftyone\pipeline\core\AggregateException;
use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\CallbackUrl;
use fiftyone\pipeline\core\ClientSideResponse;
use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\Evidence;
use fiftyone\pipeline\core\EvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\HttpRequest;
use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\JsonBundlerElement;
use fiftyone\pipeline\core\LicenseKey;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\SequenceElement;
use fiftyone\pipeline\core\SetHeaderElement;
use fiftyone\pipeline\core\Utils;
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
        AggregateException::class => 'features/exception-handling.md',
        AspectPropertyValue::class => 'features/properties.md#null-values',
        BasicListEvidenceKeyFilter::class => 'features/advertize-accepted-evidence.md',
        CallbackUrl::class => 'pipeline-elements/javascript-builder.md#callback-url',
        ClientSideResponse::class => 'features/web-integration.md#client-side-features',
        ElementData::class => 'conceptual-overview.md#element-data',
        ElementDataDictionary::class => 'conceptual-overview.md#element-data',
        Evidence::class => 'features/evidence.md',
        EvidenceKeyFilter::class => 'features/advertize-accepted-evidence.md',
        FlowData::class => 'conceptual-overview.md#flow-data',
        FlowElement::class => 'conceptual-overview.md#flow-element',
        HttpRequest::class => 'features/web-integration.md#populating-evidence',
        JavascriptBuilderElement::class => 'pipeline-elements/javascript-builder.md',
        JsonBundlerElement::class => 'pipeline-elements/json-builder.md',
        LicenseKey::class => 'features/logging.md#license-keys',
        Logger::class => 'features/logging.md',
        Pipeline::class => 'conceptual-overview.md#pipeline',
        PipelineBuilder::class => 'conceptual-overview.md#pipeline-builder',
        SequenceElement::class => 'pipeline-elements/sequence-element.md',
        SetHeaderElement::class => 'pipeline-elements/set-headers-element.md',
        Utils::class => 'features/web-integration.md',
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
