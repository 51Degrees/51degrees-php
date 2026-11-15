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

/*
 * @example OnPremiseEngine.php
 *
 * This example creates an on-premise engine, being a flow element whose
 * results come from a data file it reads when it is made. The engine gives
 * the star sign for a date of birth. The dates of each star sign are read
 * from starsigns.csv beside this example rather than written in code, as
 * they are in the simple flow element of
 * pipeline/core/examples/CustomFlowElement.php.
 *
 * The example needs no settings, no resource key and no network. Run it
 * from the package folder after composer install.
 *
 *     php examples/OnPremiseEngine.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\Engine;

//! [class]
class StarSignEngine extends Engine
{
    // The evidence holding the date of birth as YYYY-MM-DD, which is how an
    // HTML date input sends it.
    public const DATE_OF_BIRTH = 'query.dateOfBirth';

    // The results are under this key in the flow data.
    public string $dataKey = 'starsign';

    public array $properties = [
        'starsign' => [
            'name' => 'StarSign',
            'type' => 'string',
            'description' => 'The star sign for the date of birth.'
        ]
    ];

    /**
     * Each star sign's name, first day and last day, the days as MM-DD.
     *
     * @var array<array<string>>
     */
    private array $starSigns;

    /**
     * @param string $dataFile The star signs, one a line, each as its name,
     *     first day and last day, the days as MM-DD
     */
    public function __construct(string $dataFile)
    {
        parent::__construct();
        $this->starSigns = self::readDataFile($dataFile);
    }

    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter([self::DATE_OF_BIRTH]);
    }

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new AspectDataDictionary($this, [
            'starsign' => $this->getStarSign(
                $flowData->evidence->get(self::DATE_OF_BIRTH)
            )
        ]));
    }

    /**
     * The star sign for a date of birth, or no value and the reason.
     *
     * @param mixed $dateOfBirth The evidence, or null where there is none
     */
    private function getStarSign($dateOfBirth): AspectPropertyValue
    {
        if ($dateOfBirth === null || $dateOfBirth === '') {
            return new AspectPropertyValue(sprintf(
                "No date of birth was given in the evidence '%s'.",
                self::DATE_OF_BIRTH
            ));
        }
        $date = DateTime::createFromFormat('!Y-m-d', (string) $dateOfBirth);
        if ($date === false || $date->format('Y-m-d') !== $dateOfBirth) {
            return new AspectPropertyValue(sprintf(
                "The date of birth '%s' is not a date written as YYYY-MM-DD.",
                $dateOfBirth
            ));
        }
        $day = $date->format('m-d');
        foreach ($this->starSigns as [$name, $first, $last]) {
            // Capricorn runs from December into January, so its first day
            // comes after its last.
            $within = $first <= $last
                ? $day >= $first && $day <= $last
                : $day >= $first || $day <= $last;
            if ($within) {
                return new AspectPropertyValue(null, $name);
            }
        }

        return new AspectPropertyValue(
            "The data file has no star sign for the day '{$day}'."
        );
    }

    /**
     * @return array<array<string>>
     * @throws Exception The file could not be read
     */
    private static function readDataFile(string $dataFile): array
    {
        $lines = @file($dataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new Exception(
                "The star sign data file '{$dataFile}' could not be read."
            );
        }
        $starSigns = [];
        foreach ($lines as $line) {
            $starSigns[] = array_map('trim', explode(',', $line));
        }

        return $starSigns;
    }
}
//! [class]

//! [usage]
// Make the engine from the data file beside this example, and a pipeline
// holding it.
$pipeline = (new PipelineBuilder())
    ->add(new StarSignEngine(__DIR__ . '/starsigns.csv'))
    ->build();

$dateOfBirth = '1992-12-18';
$flowData = $pipeline->createFlowData();
$flowData->evidence->set(StarSignEngine::DATE_OF_BIRTH, $dateOfBirth);
$flowData->process();
$starSign = $flowData->starsign->starsign;
echo "With a date of birth of {$dateOfBirth}, the star sign is " .
    "{$starSign->value}.\n";

// With no date of birth the engine gives no value and says why. Reading
// value where there is none raises an exception with the same message, so
// check hasValue first.
$flowData = $pipeline->createFlowData();
$flowData->process();
$starSign = $flowData->starsign->starsign;
if (!$starSign->hasValue) {
    echo 'With no date of birth, the star sign has no value. ' .
        "{$starSign->noValueMessage}\n";
}
//! [usage]

// Find out more, the links for the person running this example to follow
// up.
$findOutMore = [
    'This example' =>
        'https://github.com/51Degrees/51degrees-php/tree/main/pipeline/engines',
    'An on-premise engine for device detection' =>
        'https://github.com/51Degrees/51degrees-php/tree/main/device-detection/onpremise',
    'The Pipeline API specification' =>
        'https://github.com/51Degrees/specifications/tree/main/pipeline-specification',
    'Creating an on-premise engine' =>
        'https://51degrees.com/documentation/_pipeline_api__examples__custom_element__on_premise.html?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-onpremiseengine.php&utm_term=find-out-more-on-premise-engine',
    'Contact us' =>
        'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-onpremiseengine.php&utm_term=find-out-more-contact'
];
echo "\nFind out more\n-------------\n";
foreach ($findOutMore as $label => $url) {
    echo "{$label}: {$url}\n";
}
