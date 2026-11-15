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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;

/**
 * The element that supplies the source properties the scripts of
 * DerivedProperty.php read. A real pipeline has device detection or IP
 * intelligence in this place, and this small element stands in for one so
 * that the example needs no resource key, no data file and no network.
 *
 * It reads three items of evidence and gives each as a property under the
 * element data key session. Evidence the request does not carry gives a
 * property with no value and a message saying so, which is how the example
 * shows a script giving no value and naming what was missing.
 */
class SessionElement extends FlowElement
{
    // The evidence holding how many pages the visitor has viewed.
    public const PAGES_VIEWED = 'query.pagesviewed';

    // The evidence holding how long the page has been open, in seconds.
    public const SECONDS_SINCE_PAGE_LOAD = 'query.secondssincepageload';

    // The evidence holding whether the pointer has moved, as true or false.
    public const POINTER_MOVED = 'query.pointermoved';

    // A script names these properties as session.PagesViewed and so on.
    public string $dataKey = 'session';

    // The derived property element checks, when the pipeline is built, that
    // an element lists each property a script reads.
    public array $properties = [
        'pagesviewed' => [
            'name' => 'PagesViewed',
            'type' => 'Integer',
            'description' => 'How many pages the visitor has viewed.'
        ],
        'secondssincepageload' => [
            'name' => 'SecondsSincePageLoad',
            'type' => 'Integer',
            'description' => 'How long the page has been open, in seconds.'
        ],
        'pointermoved' => [
            'name' => 'PointerMoved',
            'type' => 'Boolean',
            'description' => 'Whether the pointer has moved on the page.'
        ]
    ];

    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter([
            self::PAGES_VIEWED,
            self::SECONDS_SINCE_PAGE_LOAD,
            self::POINTER_MOVED
        ]);
    }

    public function processInternal(FlowData $flowData): void
    {
        $evidence = $flowData->evidence;
        $flowData->setElementData(new ElementDataDictionary($this, [
            'pagesviewed' => self::value(
                $evidence->get(self::PAGES_VIEWED),
                'how many pages were viewed',
                'intval'
            ),
            'secondssincepageload' => self::value(
                $evidence->get(self::SECONDS_SINCE_PAGE_LOAD),
                'how long the page has been open',
                'intval'
            ),
            'pointermoved' => self::value(
                $evidence->get(self::POINTER_MOVED),
                'whether the pointer moved',
                fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
            )
        ]));
    }

    /**
     * A property from one item of evidence, or no value where the request
     * does not carry it.
     *
     * @param mixed $evidence The evidence, or null where there is none
     * @param string $what What the evidence says, for the message
     * @param callable $convert Makes the property's type from the evidence
     */
    private static function value(
        $evidence,
        string $what,
        callable $convert
    ): AspectPropertyValue {
        if ($evidence === null) {
            return new AspectPropertyValue("The request did not say {$what}.");
        }

        return new AspectPropertyValue(null, $convert($evidence));
    }
}
