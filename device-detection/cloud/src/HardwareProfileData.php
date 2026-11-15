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

namespace fiftyone\pipeline\devicedetection;

use fiftyone\pipeline\engines\AspectDataDictionary;

/**
 * One profile the hardware profile lookup gives. It is the Aspect Data the
 * cloud Device Detection engine gives, so a profile is read with get() or
 * as a property, as device results are. It also reads as an array of
 * Property values, by name, in a foreach, with count() and with
 * json_encode(). It cannot be changed.
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
class HardwareProfileData extends AspectDataDictionary implements
    \ArrayAccess,
    \Countable,
    \IteratorAggregate,
    \JsonSerializable
{
    public const READ_ONLY_MESSAGE = 'A hardware profile cannot be changed.';

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->asDictionary()[$offset]);
    }

    /**
     * The Property's value, or null when the profile does not have it.
     *
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->asDictionary()[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws \LogicException
     */
    public function offsetSet($offset, $value): void
    {
        throw new \LogicException(self::READ_ONLY_MESSAGE);
    }

    /**
     * @param mixed $offset
     * @throws \LogicException
     */
    public function offsetUnset($offset): void
    {
        throw new \LogicException(self::READ_ONLY_MESSAGE);
    }

    public function count(): int
    {
        return count($this->asDictionary());
    }

    /**
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->asDictionary());
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->asDictionary();
    }
}
