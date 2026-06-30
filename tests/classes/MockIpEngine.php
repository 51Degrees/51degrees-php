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

namespace fiftyone\pipeline\ipintelligence\tests\classes;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\Engine;

/**
 * A stand-in for the IP intelligence on-premise engine for the translation
 * tests. It exposes CountryCodesGeographical / CountryCodesPopulation as
 * weighted string lists, with the element data key "ip" (matching the real
 * engine), so the translation engines can be exercised without the native
 * extension or a data file.
 */
class MockIpEngine extends Engine
{
    public string $dataKey = 'ip';

    /**
     * @var array<array<string, mixed>>
     */
    private array $geographical;

    /**
     * @var array<array<string, mixed>>
     */
    private array $population;

    /**
     * @param array<array<string, mixed>> $geographical weighted code list
     * @param array<array<string, mixed>> $population weighted code list
     */
    public function __construct(array $geographical, array $population)
    {
        $this->pipelines = [];
        $this->geographical = $geographical;
        $this->population = $population;
        $this->properties = [
            'countrycodesgeographical' => ['name' => 'CountryCodesGeographical', 'type' => 'Array'],
            'countrycodespopulation' => ['name' => 'CountryCodesPopulation', 'type' => 'Array'],
        ];
        parent::__construct();
    }

    public function processInternal(FlowData $flowData): void
    {
        $contents = [
            'countrycodesgeographical' => count($this->geographical) > 0
                ? new AspectPropertyValue(null, $this->geographical)
                : new AspectPropertyValue('No country codes.'),
            'countrycodespopulation' => count($this->population) > 0
                ? new AspectPropertyValue(null, $this->population)
                : new AspectPropertyValue('No country codes.'),
        ];

        $flowData->setElementData(new AspectDataDictionary($this, $contents));
    }
}
