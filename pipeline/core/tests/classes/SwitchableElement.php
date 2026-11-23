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

namespace fiftyone\pipeline\core\tests\classes;

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowElement;

/**
 * An element that fails while failing is set, as a cloud request does while
 * the service cannot be reached.
 */
class SwitchableElement extends FlowElement
{
    public static bool $failing = false;

    public string $dataKey = 'switchable';

    public array $properties = [
        'answer' => [
            'type' => 'int'
        ]
    ];

    public function processInternal($flowData): void
    {
        if (self::$failing) {
            throw new \Exception('The stand-in service cannot be reached.');
        }

        $flowData->setElementData(
            new ElementDataDictionary($this, ['answer' => 42])
        );
    }

    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter(['header.user-agent']);
    }
}
