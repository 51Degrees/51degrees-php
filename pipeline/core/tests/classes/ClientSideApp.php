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

use fiftyone\pipeline\core\ClientSideResponse;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\Utils;

/**
 * A Pipeline serving the two client-side endpoints, as a web application
 * would. It holds no PHPUnit class, so a separate PHP process can load it.
 */
class ClientSideApp
{
    public const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 '
        . 'Safari/537.36';

    public Pipeline $pipeline;

    /**
     * @param array<FlowElement> $elements Elements to add after the
     * SwitchableElement
     */
    public function __construct(array $elements = [])
    {
        $builder = (new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'endpoint' => '/json',
                'minify' => false
            ],
        ]))->add(new SwitchableElement());
        foreach ($elements as $element) {
            $builder->add($element);
        }
        $this->pipeline = $builder->build();
        // PipelineBuilder does not pass this setting on, so set it on the
        // Pipeline, where FlowData::process reads it.
        $this->pipeline->suppressProcessExceptions = true;
    }

    /**
     * The response to a GET for the JavaScript, or to a request by another
     * method for the JSON.
     *
     * @param array<string, string> $headers Request headers, by the names
     * PHP gives them in $_SERVER, such as HTTP_USER_AGENT
     */
    public function respond(
        string $method,
        array $headers = []
    ): ClientSideResponse {
        $server = array_merge([
            'REQUEST_METHOD' => $method,
            'HTTP_HOST' => 'localhost:3000',
            'HTTP_USER_AGENT' => self::CHROME,
            'REMOTE_ADDR' => '203.0.113.7',
        ], $headers);
        $flowData = $this->pipeline->createFlowData();
        $flowData->evidence->setFromWebRequest($server, [], []);

        return $method === 'GET' || $method === 'HEAD'
            ? Utils::javascriptResponse($flowData, $server)
            : Utils::jsonResponse($flowData, $server);
    }

    /**
     * The ETag of the JavaScript for a fixed request, from a new Pipeline.
     */
    public static function scriptEtag(): string
    {
        return (new self())->respond('GET')->headers['ETag'] ?? '';
    }
}
