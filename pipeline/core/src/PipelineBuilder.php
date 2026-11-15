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
 * A PipelineBuilder generates a Pipeline object
 * Before construction of the Pipeline, FlowElements are added to it
 * There are also options for how JavaScript is output from the Pipeline.
 */
class PipelineBuilder
{
    /**
     * @var array<\fiftyone\pipeline\core\Pipeline>
     */
    public array $pipelines;

    /**
     * Adds the Sequence, JSON Bundler and JavaScript Builder elements
     * together. False leaves out all three, whatever their own switches say.
     */
    public bool $addJavaScriptBuilder;
    public bool $addSequenceElement;
    public bool $addJsonBundlerElement;
    public bool $addJavaScriptBuilderElement;
    public bool $addSetHeaderElement;

    /**
     * @var array<string, mixed>
     */
    public array $javascriptBuilderSettings = [];

    /**
     * Adds the Set Header element. False leaves it out, whatever
     * addSetHeaderElement says.
     */
    public bool $useSetHeaderProperties;

    /**
     * @var array<\fiftyone\pipeline\core\FlowElement>
     */
    protected array $flowElements = [];

    /**
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [])
    {
        // List of Pipelines the FlowElement has been added to
        $this->pipelines = [];

        $this->addJavaScriptBuilder = (bool) ($settings['addJavaScriptBuilder'] ?? true);
        $this->javascriptBuilderSettings = $settings['javascriptBuilderSettings'] ?? [];
        $this->useSetHeaderProperties = (bool) ($settings['useSetHeaderProperties'] ?? true);
        $this->addSequenceElement = (bool) ($settings['addSequenceElement'] ?? true);
        $this->addJsonBundlerElement = (bool) ($settings['addJsonBundlerElement'] ?? true);
        $this->addJavaScriptBuilderElement = (bool) ($settings['addJavaScriptBuilderElement'] ?? true);
        $this->addSetHeaderElement = (bool) ($settings['addSetHeaderElement'] ?? true);

        // Passed on to the Pipeline, which reads suppressProcessExceptions
        // when it processes and autoCloseElements when it is closed.
        foreach (['suppressProcessExceptions', 'autoCloseElements'] as $name) {
            if (array_key_exists($name, $settings)) {
                $this->settings[$name] = $settings[$name];
            }
        }
    }

    /**
     * Add FlowElement to be used in Pipeline.
     *
     * @return static
     */
    public function add(FlowElement $flowElement): PipelineBuilder
    {
        $this->flowElements[] = $flowElement;

        return $this;
    }

    /**
     * Build the Pipeline from the added elements and the web integration
     * elements the switches ask for. Each of those is added only when no
     * added element has its data key, so it runs once.
     */
    public function build(): Pipeline
    {
        $present = [];
        foreach ($this->flowElements as $flowElement) {
            $present[$flowElement->dataKey] = true;
        }

        $flowElements = array_merge(
            $this->getSequenceElements($present),
            $this->withJsonBundler($present),
            $this->getJavaScriptElements($present),
            $this->getSetHeaderElements($present)
        );

        return new Pipeline($flowElements, $this->settings);
    }

    /**
     * Add an instance of the logger class to the Pipeline.
     *
     * @return static
     */
    public function addLogger(Logger $logger): PipelineBuilder
    {
        $this->settings['logger'] = $logger;

        return $this;
    }

    /**
     * Build from a JSON configuration file
     * This JSON file should have the following structure:
     * `{
     * "PipelineOptions": {
     * "Elements": [
     *  {
     *    "BuilderName": // Name of element as in use statement,
     *    "BuildParameters": {
     *      // An object of parameters passed to the constructor
     *   }
     *  }]
     * }`.
     *
     * The configured elements are then built into a Pipeline as build()
     * builds added ones, so the web integration elements the switches ask
     * for are added unless the configuration lists them.
     *
     * If an element fails to build, the elements already built from the
     * configuration are closed and the error is thrown.
     *
     * @param array<string, mixed>|string $fileOrConfig Filename of the config file to load config or associative array of config values
     */
    public function buildFromConfig($fileOrConfig): Pipeline
    {
        if (is_string($fileOrConfig)) {
            $config = json_decode(file_get_contents($fileOrConfig), true);
        } else {
            $config = $fileOrConfig;
        }

        $built = [];

        try {
            foreach ($config['PipelineOptions']['Elements'] as $element) {
                /** @phpstan-var \fiftyone\pipeline\core\FlowElement $builder */
                $builder = $element['BuilderName'];

                if (isset($element['BuildParameters'])) {
                    $flowElement = new $builder($element['BuildParameters']);
                } else {
                    $flowElement = new $builder();
                }

                $built[] = $flowElement;
                $this->flowElements[] = $flowElement;
            }

            return $this->build();
        } catch (\Throwable $e) {
            $this->closeBuilt($built);

            throw $e;
        }
    }

    /**
     * Closes the elements built from a configuration that failed. A failure
     * to close is logged rather than thrown, so the caller gets the error
     * that stopped the build.
     *
     * @param array<\fiftyone\pipeline\core\FlowElement> $flowElements
     */
    private function closeBuilt(array $flowElements): void
    {
        foreach ($flowElements as $flowElement) {
            if (!method_exists($flowElement, 'close')) {
                continue;
            }

            try {
                $flowElement->close();
            } catch (\Throwable $e) {
                if (isset($this->settings['logger'])) {
                    $this->settings['logger']->log('error', sprintf(
                        'Could not close %s after the pipeline failed to build: %s',
                        get_class($flowElement),
                        $e->getMessage()
                    ));
                }
            }
        }
    }

    /**
     * The Sequence element goes first, so every later element reads the
     * session id and sequence it writes. It is not added when the
     * application added one, because a second one would count each request
     * twice.
     *
     * @param array<string, bool> $present The data keys of the
     * application's elements
     * @return array<\fiftyone\pipeline\core\FlowElement>
     */
    private function getSequenceElements(array $present): array
    {
        if ($this->addJavaScriptBuilder && $this->addSequenceElement &&
            !isset($present['sequence'])) {
            return [new SequenceElement()];
        }

        return [];
    }

    /**
     * Whether a JSON Bundler is added, which is only when the application
     * did not add one.
     *
     * @param array<string, bool> $present The data keys of the
     * application's elements
     */
    private function addsJsonBundler(array $present): bool
    {
        return $this->addJavaScriptBuilder && $this->addJsonBundlerElement &&
            !isset($present['jsonbundler']);
    }

    /**
     * The application's elements, with an added JSON Bundler immediately
     * before a JavaScript Builder the application added, which reads it.
     *
     * @param array<string, bool> $present The data keys of the
     * application's elements
     * @return array<\fiftyone\pipeline\core\FlowElement>
     */
    private function withJsonBundler(array $present): array
    {
        if (!$this->addsJsonBundler($present) ||
            !isset($present['javascriptbuilder'])) {
            return $this->flowElements;
        }

        $flowElements = [];
        $added = false;
        foreach ($this->flowElements as $flowElement) {
            if (!$added && $flowElement->dataKey === 'javascriptbuilder') {
                $flowElements[] = new JsonBundlerElement();
                $added = true;
            }
            $flowElements[] = $flowElement;
        }

        return $flowElements;
    }

    /**
     * The JSON Bundler goes before the JavaScript Builder, which reads it.
     * Neither is added when the application added one, so each runs once.
     *
     * @param array<string, bool> $present The data keys of the
     * application's elements
     * @return array<\fiftyone\pipeline\core\FlowElement>
     */
    private function getJavaScriptElements(array $present): array
    {
        $flowElements = [];

        // withJsonBundler places the JSON Bundler when the application
        // added the JavaScript Builder.
        if ($this->addsJsonBundler($present) &&
            !isset($present['javascriptbuilder'])) {
            $flowElements[] = new JsonBundlerElement();
        }

        if ($this->addJavaScriptBuilder && $this->addJavaScriptBuilderElement &&
            !isset($present['javascriptbuilder'])) {
            $flowElements[] = new JavascriptBuilderElement($this->javascriptBuilderSettings);
        }

        return $flowElements;
    }

    /**
     * The Set Header element, unless the application added one.
     *
     * @param array<string, bool> $present The data keys of the
     * application's elements
     * @return array<\fiftyone\pipeline\core\FlowElement>
     */
    private function getSetHeaderElements(array $present): array
    {
        $flowElements = [];

        if ($this->useSetHeaderProperties && $this->addSetHeaderElement &&
            !isset($present[Constants::SETHEADER_ELEMENT_KEY])) {
            // Add SetHeader elements
            $flowElements[] = new SetHeaderElement();
        }

        return $flowElements;
    }
}
