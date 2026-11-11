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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\HttpRequest;
use fiftyone\pipeline\core\Messages;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

class FlowDataTests extends TestCase
{
    /**
     * Check that an element data can be returned from a FlowData using its
     * data key.
     */
    public function testGetWithKey()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = 'testKey';
        $data = new ElementData($element);

        $flowData = new FlowData($this->createMock(Pipeline::class));
        $flowData->setElementData($data);

        $returnedData = $flowData->get('testKey');
        $this->assertNotNull($returnedData);
    }

    /**
     * Check that an element data can be returned from a FlowData using its
     * data key directly via a "magic getter".
     */
    public function testMagicGetter()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = 'testKey';
        $data = new ElementData($element);

        $flowData = new FlowData($this->createMock(Pipeline::class));
        $flowData->setElementData($data);

        $returnedData = $flowData->testKey;
        $this->assertNotNull($returnedData);
    }

    /**
     * Check that an element data can be returned from a FlowData using the
     * getFromElement method.
     */
    public function testGetFromElement()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = 'testKey';
        $data = new ElementData($element);

        $flowData = new FlowData($this->createMock(Pipeline::class));
        $flowData->setElementData($data);

        $returnedData = $flowData->getFromElement($element);
        $this->assertNotNull($returnedData);
    }

    /**
     * Check that an exception is thrown when fetching a key which does not
     * exist in the FlowData, and that the correct error message is returned.
     */
    public function testMissingKey()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = 'testKey';
        $data = new ElementData($element);

        $flowData = new FlowData($this->createMock(Pipeline::class));
        $flowData->setElementData($data);
        
        $this->expectExceptionMessage(sprintf(Messages::NO_ELEMENT_DATA, 'otherKey', 'testKey'));
        $flowData->get('otherKey');
    }

    /**
     * Check that an exception is thrown when fetching a key through a magic
     * getter which does not exist in the FlowData, and that the correct error
     * message is returned.
     */
    public function testMissingKeyMagicGetter()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = 'testKey';
        $data = new ElementData($element);

        $flowData = new FlowData($this->createMock(Pipeline::class));
        $flowData->setElementData($data);
        
        $this->expectExceptionMessage(sprintf(Messages::NO_ELEMENT_DATA, 'otherKey', 'testKey'));
        $flowData->otherKey;
    }

    /**
     * Check that an exception is thrown when fetching a key using the
     * getFromElement method which does not exist in the FlowData, and that the
     * correct error message is returned.
     */
    public function testMissingKeyFromElement()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = 'testKey';
        $data = new ElementData($element);

        $element2 = $this->createMock(FlowElement::class);
        $element2->dataKey = 'otherKey';

        $flowData = new FlowData($this->createMock(Pipeline::class));
        $flowData->setElementData($data);
        
        $this->expectExceptionMessage(sprintf(Messages::NO_ELEMENT_DATA, 'otherKey', 'testKey'));
        $flowData->getFromElement($element2);
    }

    /**
     * Evidence keys ignore letter case, so an element reads a value that was
     * set under its key in other letters.
     */
    public function testAnElementReadsEvidenceSetInOtherLetters()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('Query.Session-ID', 'abc');
        $flowData->evidence->set('QUERY.SEQUENCE', '5');
        $flowData->process();

        $this->assertSame('abc', $flowData->sequence->get('session-id'));
        $this->assertSame(6, $flowData->sequence->get('sequence'));
    }

    /**
     * A key that differs from one already set only in letter case is the
     * same key, so its value replaces the first, under the first key's
     * letters.
     */
    public function testAKeyInOtherLettersIsTheSameKey()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.Name', 'first');
        $flowData->evidence->set('QUERY.name', 'second');

        $this->assertSame('second', $flowData->evidence->get('query.name'));
        $this->assertSame('second', $flowData->evidence->get('Query.NAME'));
        $this->assertSame(['query.Name' => 'second'], array_filter(
            $flowData->evidence->getAll(),
            fn ($key) => strcasecmp($key, 'query.name') === 0,
            ARRAY_FILTER_USE_KEY
        ));
    }

    /**
     * A key no element accepts, set again in other letters, is the same
     * key, so its value replaces the first under the first key's letters,
     * as an accepted key's does, and get() still answers only accepted
     * values.
     */
    public function testAKeyNoElementAcceptsInOtherLettersIsTheSameKey()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('header.X-Not-Used', 'first');
        $flowData->evidence->set('HEADER.x-not-used', 'second');

        $this->assertSame(['header.X-Not-Used' => 'second'], array_filter(
            $flowData->evidence->getAllIncludingUnaccepted(),
            fn ($key) => strcasecmp($key, 'header.x-not-used') === 0,
            ARRAY_FILTER_USE_KEY
        ));
        $this->assertNull($flowData->evidence->get('header.x-not-used'));
    }

    /**
     * A key that has left the store answers null and raises nothing, so
     * the index of spellings never has to stay in step with the store.
     */
    public function testAKeyThatLeftTheStoreAnswersNull()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.Name', 'first');

        // Takes the key out of the store alone, as a subclass of Evidence
        // can, leaving the spelling it was set with behind.
        $store = new \ReflectionProperty($flowData->evidence, 'evidence');
        $store->setAccessible(true);
        $store->setValue($flowData->evidence, []);

        $raised = [];
        set_error_handler(function ($number, $message) use (&$raised) {
            $raised[] = $message;

            return true;
        });
        try {
            $value = $flowData->evidence->get('QUERY.name');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised);
        $this->assertNull($value);
    }

    /**
     * The web request's view is found under its key in any letter case.
     */
    public function testTheWebRequestIsFoundInOtherLetters()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->setFromWebRequest(['REQUEST_METHOD' => 'GET'], [], []);

        $this->assertInstanceOf(
            HttpRequest::class,
            $flowData->evidence->get('Server.HTTP-Request')
        );
    }
}
