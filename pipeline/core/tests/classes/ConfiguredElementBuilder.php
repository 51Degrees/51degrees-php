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

/**
 * A builder of ConfiguredElement, with a set method and an add method, as
 * the element builders of the packages have.
 */
class ConfiguredElementBuilder
{
    private string $dataKey = 'configured';

    private int $answer = 0;

    public function setDataKey(string $dataKey): self
    {
        $this->dataKey = $dataKey;

        return $this;
    }

    public function setAnswer(int $answer): self
    {
        $this->answer = $answer;

        return $this;
    }

    public function addToAnswer(int $amount): self
    {
        $this->answer += $amount;

        return $this;
    }

    public function build(): ConfiguredElement
    {
        return new ConfiguredElement([
            'dataKey' => $this->dataKey,
            'answer' => $this->answer
        ]);
    }
}
