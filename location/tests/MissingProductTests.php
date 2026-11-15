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

namespace fiftyone\pipeline\geolocation\tests;

use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\geolocation\GeoLocationPipelineBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A Resource Key whose products hold nothing for location still builds the
 * Pipeline. Each request gives empty location data and no error, and the
 * missing product is logged once, at warning level, naming the Data Key, as
 * cloud-aspect-engine.md's Start-up activity and Processing sections say.
 * The cloud service is replaced, so no key or network is needed.
 */
class MissingProductTests extends TestCase
{
    /**
     * @var array<array<string, string>>
     */
    private array $logged = [];

    /**
     * @param array<string, string> $log
     */
    public function record(array $log): void
    {
        $this->logged[] = $log;
    }

    public static function provider_providers(): array
    {
        return [
            '51Degrees' => ['fiftyonedegrees', 'location'],
            'Digital Element' => ['digitalelement', 'location_digitalelement']
        ];
    }

    /**
     * @dataProvider provider_providers
     */
    #[DataProvider('provider_providers')]
    public function testAKeyWithoutTheProductBuildsAndGivesEmptyData(
        string $provider,
        string $dataKey
    ): void {
        $pipeline = $this->pipeline($provider);

        for ($request = 0; $request < 3; $request++) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('query.51D_Pos_latitude', '51.4578261');
            $flowData->evidence->set('query.51D_Pos_longitude', '-0.975922996290084');
            $flowData->process();

            $this->assertSame([], $flowData->errors, "request {$request}");
            $this->assertSame([], $flowData->get($dataKey)->asDictionary());
        }

        $warnings = array_values(array_filter(
            $this->logged,
            fn ($log) => strpos($log['message'], $dataKey) !== false
        ));
        $this->assertCount(1, $warnings, print_r($this->logged, true));
        $this->assertSame('warning', $warnings[0]['level']);
    }

    private function pipeline(string $provider)
    {
        $this->logged = [];
        $test = $this;
        $logger = new class ('trace', ['test' => $test]) extends Logger {
            public function logInternal(array $log): void
            {
                $this->settings['test']->record($log);
            }
        };

        // A cloud service whose Resource Key holds only a device product.
        $client = new class extends HttpClient {
            public function makeCloudRequest(string $type, string $url, ?string $content, ?string $originHeader): string
            {
                if (strpos($url, 'accessibleProperties') !== false) {
                    return '{"Products": {"device": {"DataTier": "tier", "Properties": [{"Name": "IsMobile", "Type": "Boolean", "Category": "Device"}]}}}';
                }
                if (strpos($url, 'evidencekeys') !== false) {
                    return '["query.51D_Pos_latitude", "query.51D_Pos_longitude", "header.user-agent"]';
                }
                if (strpos($url, '.json') !== false) {
                    return '{"device": {"ismobile": true}, "javascriptProperties": []}';
                }

                throw new \Exception("Not expecting a request to '{$url}'");
            }
        };

        return (new GeoLocationPipelineBuilder([
            'resourceKey' => 'mock_resource_key',
            'locationProvider' => $provider,
            'httpClient' => $client
        ]))->addLogger($logger)->build();
    }
}
