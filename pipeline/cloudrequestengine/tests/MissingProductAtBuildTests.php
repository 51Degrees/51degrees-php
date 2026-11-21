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
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * A cloud engine whose product the Resource Key does not include logs it
 * when the Pipeline is built, where the Cloud Request Engine has its
 * accessible properties by then, and on first use where it does not.
 */
class MissingProductAtBuildTests extends TestCase
{
    private const PROPERTIES = '{"Products": {"device": {"Properties": [' .
        '{"Name": "IsMobile", "Type": "Boolean"}]}}}';
    private const EVIDENCE_KEYS = '["query.User-Agent"]';
    private const DATA = '{"device":{"ismobile":true}}';
    private const MISSING = "no product for 'location'";

    private bool $propertiesFail = false;

    public function testAMissingProductIsLoggedWhenThePipelineIsBuilt(): void
    {
        $logger = self::memoryLogger();
        $pipeline = $this->pipeline($logger);

        $this->assertSame(1, self::missingProductWarnings($logger));

        self::process($pipeline);
        self::process($pipeline);

        $this->assertSame(1, self::missingProductWarnings($logger));
    }

    public function testAfterATransientFailureItIsLoggedOnFirstUse(): void
    {
        $this->propertiesFail = true;
        $logger = self::memoryLogger();
        $pipeline = $this->pipeline($logger);

        $this->assertSame(0, self::missingProductWarnings($logger));

        $this->propertiesFail = false;
        self::process($pipeline);
        self::process($pipeline);

        $this->assertSame(1, self::missingProductWarnings($logger));
    }

    /**
     * A Pipeline with a Cloud Request Engine whose Resource Key includes the
     * device product only, and a cloud engine for location.
     */
    private function pipeline(Logger $logger): Pipeline
    {
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url): string {
                $lower = strtolower($url);
                if (strpos($lower, 'accessibleproperties') !== false) {
                    if ($this->propertiesFail) {
                        throw new CloudRequestException('down', 503);
                    }

                    return self::PROPERTIES;
                }
                if (strpos($lower, 'evidencekeys') !== false) {
                    return self::EVIDENCE_KEYS;
                }

                return self::DATA;
            }
        );
        $location = new CloudEngine();
        $location->dataKey = 'location';

        return (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->addLogger($logger)
            ->add(new CloudRequestEngine([
                'resourceKey' => 'resourcekey',
                'httpClient' => $client,
            ]))
            ->add($location)
            ->build();
    }

    private static function process(Pipeline $pipeline): void
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');
        $flowData->process();
    }

    private static function missingProductWarnings(Logger $logger): int
    {
        return count(array_filter(
            $logger->entries,
            static fn (array $entry): bool => $entry['level'] === 'warning' &&
                strpos($entry['message'], self::MISSING) !== false
        ));
    }

    /**
     * A logger that keeps every entry, at every level.
     */
    private static function memoryLogger(): Logger
    {
        return new class ('trace') extends Logger {
            /** @var array<array<string, string>> */
            public array $entries = [];

            public function logInternal(array $log): void
            {
                $this->entries[] = $log;
            }
        };
    }
}
