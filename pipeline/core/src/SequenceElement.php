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

namespace fiftyone\pipeline\core;

/**
 * The SequenceElement stores session data regarding
 * requests for client side JavaScript from the JavaScript
 * created by a Pipeline's JavaScriptBuilder
 * If a Pipeline is constructed with the JavaScript elements
 * enabled this is added automatically along with the JavaScriptBuilder
 * and JSONBundler.
 */
class SequenceElement extends FlowElement
{
    public string $dataKey = 'sequence';

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $properties = [
        'session-id' => ['name' => 'session-id', 'type' => 'string'],
        'sequence' => ['name' => 'sequence', 'type' => 'int'],
    ];

    /**
     * The SequenceElement uses query.sequence and query.session-id evidence.
     */
    public function getEvidenceKeyFilter(): EvidenceKeyFilter
    {
        $filter = new EvidenceKeyFilter();

        $filter->filterEvidenceKey = function ($key) {
            if ($key === 'query.sequence' || $key === 'query.session-id') {
                return true;
            }

            return false;
        };

        return $filter;
    }

    /**
     * The SequenceElement stores session data for requests for JavaScript.
     * It writes the session id and sequence to Evidence, under
     * query.session-id and query.sequence, and to its Element Data, under
     * session-id and sequence.
     */
    public function processInternal(FlowData $flowData): void
    {
        // The session id and the sequence are each taken from Evidence
        // independently. A value that is absent, or not a string (a repeated
        // query parameter gives an array), is treated as absent. A sequence
        // that is not a positive 32 bit integer is also treated as absent,
        // as the JavaScript builder renders any such value as 1.
        $sessionId = $flowData->evidence->get('query.session-id');
        if (!is_string($sessionId) || $sessionId === '') {
            $sessionId = uniqid();
        }

        $sequence = JavascriptBuilderElement::parseSequence(
            $flowData->evidence->get('query.sequence')
        );
        $sequence = $sequence === null ? 1 : $sequence + 1;

        $flowData->evidence->set('query.session-id', $sessionId);
        $flowData->evidence->set('query.sequence', $sequence);

        $flowData->setElementData(new ElementDataDictionary($this, [
            'session-id' => $sessionId,
            'sequence' => $sequence,
        ]));
    }
}
