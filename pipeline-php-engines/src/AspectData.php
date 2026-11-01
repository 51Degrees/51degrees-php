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

namespace fiftyone\pipeline\engines;

use fiftyone\pipeline\core\ElementData;

/**
 * AspectData extends ElementData by adding the option of a missing property service
 * It also allows properties to be explicitly excluded by a FlowElement / Engine.
 *
 * @property \fiftyone\pipeline\engines\Engine $flowElement
 */
class AspectData extends ElementData
{
    public ?MissingPropertyService $missingPropertyService = null;

    /**
     * Constructor for element data
     * Adds default missing property service if not available.
     */
    public function __construct(Engine $flowElement)
    {
        if ($this->missingPropertyService === null) {
            $this->missingPropertyService = new MissingPropertyService();
        }

        parent::__construct($flowElement);
    }

    /**
     * Get a value (unless in a flowElement's restrictedProperties list)
     * If property not found, call the attached missing property service.
     *
     * @return mixed
     */
    public function get(string $key)
    {
        try {
            $result = $this->getInternal($key);
            
            if (empty($result)) {
                return $this->missingPropertyService->check($key, $this->flowElement);
            }

            return $result;
        } catch (\Exception $e) {
            return $this->missingPropertyService->check($key, $this->flowElement);
        }
    }
}
