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

use fiftyone\pipeline\core\AggregateException;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\DeviceDetectionCloud;
use fiftyone\pipeline\devicedetection\HardwareProfileCloud;
use fiftyone\pipeline\devicedetection\tests\classes\FakeCloudRequestEngine;
use PHPUnit\Framework\TestCase;

/**
 * These tests run against fixed cloud responses, so they need no network
 * connection and no resource key. They cover the case that used to make the
 * TAC and native model examples fill the console with warnings, which is a
 * resource key that returns the hardware profiles but is not entitled to
 * the hardware properties themselves.
 */
class HardwareProfileCloudTest extends TestCase
{
    // A response of the shape the cloud service returns for a TAC lookup
    // when the resource key has the hardware aspect but is not entitled to
    // the hardware properties. The values are null at the aspect level and
    // each one is paired with a reason. The profiles carry only the
    // properties the key is entitled to.
    private const NOT_ENTITLED_RESPONSE = <<<'JSON'
        {
            "hardware": {
                "profiles": [
                    {
                        "devicetype": "SmartPhone",
                        "ismobile": true
                    }
                ],
                "hardwarevendor": null,
                "hardwarevendornullreason": "HardwareVendor is a paid feature. You need a licence key to retrieve data.",
                "hardwarename": null,
                "hardwarenamenullreason": "HardwareName is a paid feature. You need a licence key to retrieve data.",
                "hardwaremodel": null,
                "hardwaremodelnullreason": "HardwareModel is a paid feature. You need a licence key to retrieve data."
            }
        }
        JSON;

    // A response from a fully entitled resource key.
    private const ENTITLED_RESPONSE = <<<'JSON'
        {
            "hardware": {
                "profiles": [
                    {
                        "hardwarevendor": "Apple",
                        "hardwarename": ["iPhone 6"],
                        "hardwaremodel": "A1586"
                    }
                ]
            }
        }
        JSON;

    // A response where the hardware aspect is absent altogether, which is
    // what a resource key without the hardware aspect returns.
    private const NO_HARDWARE_RESPONSE = '{"device": {"ismobile": true}}';

    // A TAC lookup response with one profile. 'hardwarevendor' has a value,
    // 'hardwaremodel' is null, and 'hardwarefamily' has no value and a
    // reason at the aspect level, which the engine carries into each
    // profile.
    private const PROFILES_RESPONSE = <<<'JSON'
        {
            "hardware": {
                "profiles": [
                    {
                        "hardwarevendor": "Coolpad",
                        "hardwarename": ["5217"],
                        "hardwaremodel": null
                    }
                ],
                "hardwarefamily": null,
                "hardwarefamilynullreason": "HardwareFamily is a paid feature. You need a licence key to retrieve data."
            }
        }
        JSON;

    // The hardware product as the cloud request engine finds it from the
    // accessibleproperties endpoint, with its one Property, 'Profiles'.
    private const HARDWARE_PRODUCT = [
        'hardware' => [
            'profiles' => [
                'name' => 'Profiles',
                'type' => 'Array',
                'itemproperties' => [
                    ['name' => 'HardwareVendor', 'type' => 'String'],
                    ['name' => 'HardwareName', 'type' => 'Array'],
                    ['name' => 'HardwareModel', 'type' => 'String'],
                    ['name' => 'HardwareFamily', 'type' => 'String'],
                ],
            ],
        ],
    ];

    public function testNullReasonIsCarriedIntoEachProfile()
    {
        $hardware = $this->process(self::NOT_ENTITLED_RESPONSE);

        $this->assertCount(1, $hardware->profiles);

        $profile = $hardware->profiles[0];

        foreach (['hardwarevendor', 'hardwarename', 'hardwaremodel'] as $name) {
            $this->assertArrayHasKey(
                $name,
                $profile,
                "'{$name}' should be present on the profile so the reason " .
                'it has no value can be reported'
            );
            $this->assertFalse($profile[$name]->hasValue);
            $this->assertStringContainsString(
                'paid feature',
                $profile[$name]->noValueMessage
            );
        }
    }

    public function testNullReasonIsReportedByTheExampleHelper()
    {
        $hardware = $this->process(self::NOT_ENTITLED_RESPONSE);
        $profile = $hardware->profiles[0];

        $this->assertSame(
            'Unknown (HardwareVendor is a paid feature. You need a licence ' .
            'key to retrieve data.)',
            ExampleUtils::getHumanReadable($profile, 'hardwarevendor')
        );
    }

    public function testEntitledValuesAreReportedAsValues()
    {
        $hardware = $this->process(self::ENTITLED_RESPONSE);
        $profile = $hardware->profiles[0];

        $this->assertSame(
            'Apple',
            ExampleUtils::getHumanReadable($profile, 'hardwarevendor')
        );
        // A list value is joined for display.
        $this->assertSame(
            'iPhone 6',
            ExampleUtils::getHumanReadable($profile, 'hardwarename')
        );
        $this->assertSame(
            'A1586',
            ExampleUtils::getHumanReadable($profile, 'hardwaremodel')
        );
    }

    public function testAbsentHardwareAspectGivesNoProfilesRatherThanAnError()
    {
        $hardware = $this->process(self::NO_HARDWARE_RESPONSE);

        $this->assertCount(0, ExampleUtils::getProfiles($hardware));
    }

    /**
     * A lookup that matches nothing gives an empty list of profiles, which
     * the JSON holds, so a page can tell it from a lookup that did not run.
     */
    public function testALookupThatMatchesNothingGivesAnEmptyListOfProfiles()
    {
        $response = '{"hardware": {"profiles": []}}';

        $this->assertSame([], $this->process($response)->get('profiles'));
        $json = $this->json($response);
        $this->assertArrayHasKey('profiles', $json);
        $this->assertSame([], $json['profiles']);
    }

    public function testMissingPropertyIsNamedInTheMessage()
    {
        $hardware = $this->process(self::ENTITLED_RESPONSE);
        $profile = $hardware->profiles[0];

        $message = ExampleUtils::getHumanReadable($profile, 'screenpixelswidth');

        $this->assertStringContainsString('screenpixelswidth', $message);
        $this->assertStringStartsWith('Unknown (', $message);
    }

    public function testLogsAMissingHardwareProductOnceAtWarningLevel()
    {
        $logged = $this->logs([], 3);

        $this->assertCount(1, $logged);
        $this->assertSame('warning', $logged[0]['level']);
        $this->assertStringContainsString('hardware', $logged[0]['message']);
    }

    /**
     * When the cloud request fails, reading the profiles gives the
     * CloudRequestFailed reason, and the error is the cloud request's.
     */
    public function testAFailedCloudRequestGivesCloudRequestFailed()
    {
        $cloud = new class ('') extends FakeCloudRequestEngine {
            public function processInternal(FlowData $flowData): void
            {
                throw new \Exception('The cloud request failed.');
            }
        };
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => true,
        ]))
            ->add($cloud)
            ->add(new HardwareProfileCloud())
            ->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame(['cloud-response'], array_keys($flowData->errors));
        $message = null;
        try {
            $flowData->get('hardware')->get('profiles');
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }
        $this->assertSame(
            "Property 'profiles' not found in data for element 'hardware'. "
            . 'This is because an upstream cloud request failed, so no data '
            . 'was available to populate the property.',
            $message
        );
    }

    /**
     * Completes testAFailedCloudRequestGivesCloudRequestFailed. When the
     * cloud request fails, both Device Detection cloud engines give the
     * CloudRequestFailed reason, naming their own Data Key, for every
     * property: one their metadata lists, the same in another case, and one
     * it does not list. The Flow Data holds the cloud request's error alone,
     * and the error is the one the cloud request gave.
     */
    public function testAFailedCloudRequestGivesCloudRequestFailedForEveryPropertyOfEachEngine()
    {
        $cloud = new class ('') extends FakeCloudRequestEngine {
            public function processInternal(FlowData $flowData): void
            {
                throw new \Exception('The cloud request failed.');
            }
        };
        $cloud->flowElementProperties = self::HARDWARE_PRODUCT + [
            'device' => [
                'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean'],
            ],
        ];
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => true,
        ]))
            ->add($cloud)
            ->add(new DeviceDetectionCloud())
            ->add(new HardwareProfileCloud())
            ->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame(['cloud-response'], array_keys($flowData->errors));
        $error = $flowData->errors['cloud-response'];
        $this->assertNotInstanceOf(AggregateException::class, $error);
        $this->assertSame('The cloud request failed.', $error->getMessage());

        $reads = [
            'device' => ['ismobile', 'IsMobile', 'ISMOBILE', 'nosuchproperty'],
            'hardware' => ['profiles', 'Profiles', 'nosuchproperty'],
        ];
        foreach ($reads as $dataKey => $names) {
            foreach ($names as $name) {
                $message = null;
                try {
                    $flowData->get($dataKey)->get($name);
                } catch (\Exception $e) {
                    $message = $e->getMessage();
                }
                $this->assertSame(
                    "Property '{$name}' not found in data for element '{$dataKey}'. "
                    . 'This is because an upstream cloud request failed, so no '
                    . 'data was available to populate the property.',
                    $message,
                    "{$dataKey}.{$name}"
                );
            }
        }
    }

    public function testLogsNothingWhenTheHardwareProductIsPresent()
    {
        $logged = $this->logs([
            'hardware' => [
                'profiles' => ['name' => 'Profiles', 'type' => 'array']
            ]
        ], 1);

        $this->assertSame([], $logged);
    }

    public function testTheJsonListsTheProfiles()
    {
        $json = $this->json(self::PROFILES_RESPONSE);

        $this->assertArrayHasKey(
            'profiles',
            $json,
            "The JSON for 'hardware' should list the profiles"
        );
        $this->assertCount(1, $json['profiles']);
    }

    public function testEachProfileInTheJsonFollowsTheRulesForNoValueAndNull()
    {
        $json = $this->json(self::PROFILES_RESPONSE);
        $this->assertArrayHasKey('profiles', $json);

        $profile = $json['profiles'][0];
        ksort($profile);

        // The value is written, no value is null with its reason, and the
        // null has no entry.
        $this->assertSame([
            'hardwarefamily' => null,
            'hardwarefamilynullreason' => 'HardwareFamily is a paid feature. You need a licence key to retrieve data.',
            'hardwarename' => ['5217'],
            'hardwarevendor' => 'Coolpad',
        ], $profile);
    }

    /**
     * The 'hardware' entry of the JSON Bundler's output for a fixed cloud
     * response, through a resource key that carries the hardware product, as
     * the web page receives it after JSON encoding.
     *
     * @return array<string, mixed>
     */
    private function json(string $response): array
    {
        $cloud = new FakeCloudRequestEngine($response);
        $cloud->flowElementProperties = self::HARDWARE_PRODUCT;
        $pipeline = (new PipelineBuilder())
            ->add($cloud)
            ->add(new HardwareProfileCloud())
            ->build();

        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame([], $flowData->errors);

        $text = json_encode($flowData->jsonbundler->json['hardware']);
        $this->assertIsString($text, json_last_error_msg());

        return json_decode($text, true);
    }

    /**
     * Process the entitled response the given number of times, with the
     * given products discovered, and return what was logged.
     *
     * @param array<string, array<string, mixed>> $products
     * @return array<array<string, string>>
     */
    private function logs(array $products, int $requests): array
    {
        $logger = new class ('trace', []) extends Logger {
            /**
             * @var array<array<string, string>>
             */
            public array $logged = [];

            public function logInternal(array $log): void
            {
                $this->logged[] = $log;
            }
        };
        $cloud = new FakeCloudRequestEngine(self::ENTITLED_RESPONSE);
        $cloud->flowElementProperties = $products;
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->addLogger($logger)
            ->add($cloud)
            ->add(new HardwareProfileCloud())
            ->build();

        for ($request = 0; $request < $requests; $request++) {
            $pipeline->createFlowData()->process();
        }

        return $logger->logged;
    }

    /**
     * Run a fixed cloud response through the hardware profile engine and
     * return the resulting element data.
     *
     * @return \fiftyone\pipeline\engines\AspectDataDictionary
     */
    private function process(string $response)
    {
        $pipeline = (new PipelineBuilder())
            ->add(new FakeCloudRequestEngine($response))
            ->add(new HardwareProfileCloud())
            ->build();

        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame(
            [],
            $flowData->errors,
            'Processing a fixed cloud response should not raise an error'
        );

        return $flowData->hardware;
    }
}
