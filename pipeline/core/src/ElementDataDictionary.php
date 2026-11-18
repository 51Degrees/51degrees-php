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
 * An extension of ElementData with dictionary object storage / lookup.
 */
class ElementDataDictionary extends ElementData
{
    /**
     * The values by name. It can still be read as the public contents
     * property, which gives a copy, so changing one value of that copy
     * changes nothing and PHP raises a notice. Setting the whole property
     * is a write, which throws once the FlowElements that write the data
     * have finished.
     *
     * @var array<string, mixed>
     */
    protected array $contents;

    /**
     * Constructor for element data dictionary.
     *
     * @param array<string, mixed> $contents Dictionary contents
     */
    public function __construct(FlowElement $flowElement, array $contents)
    {
        $this->contents = $contents;

        parent::__construct($flowElement);
    }

    /**
     * The contents, or else as for ElementData.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        if ($key === 'contents') {
            return $this->contents;
        }

        return parent::__get($key);
    }

    /**
     * Sets the contents, or else as for ElementData. Either is a write, so
     * it throws once the FlowElements that write the data have finished.
     *
     * @param mixed $value
     * @throws \Exception once the FlowElements that write it have finished
     */
    public function __set(string $key, $value): void
    {
        if ($key === 'contents') {
            $this->checkWritesNotFinished($key);
            $this->contents = $value;

            return;
        }

        parent::__set($key, $value);
    }

    /**
     * Whether the contents, or a property set through the data, is there.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return $key === 'contents' || parent::__isset($key);
    }

    /**
     * Get the values contained in the ElementData instance as a dictionary
     * of keys and values.
     *
     * @return array<string, mixed>>
     */
    public function asDictionary(): array
    {
        return $this->contents;
    }

    /**
     * Internal getter for contents, finding the name ignoring case as every
     * string key is compared.
     *
     * @return mixed
     */
    public function getInternal(string $key)
    {
        if (array_key_exists($key, $this->contents)) {
            return $this->contents[$key];
        }

        foreach ($this->contents as $name => $value) {
            if (strcasecmp((string) $name, $key) === 0) {
                return $value;
            }
        }

        return null;
    }
}
