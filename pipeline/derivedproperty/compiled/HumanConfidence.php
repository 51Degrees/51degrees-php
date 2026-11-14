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

// Written by ci/compile-built-in-scripts.php from scripts/HumanConfidence.yaml. Do not edit.

return array (
  'sha256' => '2440f4cf6ce2672326ad0be140d92d328fb2418297291a36fdfafed8bd04d861',
  'script' => 
  array (
    'format' => 1,
    'name' => 'HumanConfidence',
    'version' => '0.8.0',
    'deprecated' => false,
    'deprecationNote' => NULL,
    'output' => 
    array (
      'Name' => 'HumanConfidence',
      'Description' => 'The confidence that the request came from a device being used by a human who is viewing the page. Returns one of High, Medium, Low or Unknown. Whether the browser window was in view cannot be known from the request alone, so until the 51Degrees JavaScript has run the answer is Unknown.',
      'ValueType' => 'string',
      'IsList' => false,
      'Values' => 
      array (
        0 => 
        array (
          'Name' => 'High',
          'Description' => 'High confidence that the request is from a device being used by a human who is viewing the page.',
        ),
        1 => 
        array (
          'Name' => 'Medium',
          'Description' => 'The evidence is mixed and the request cannot be placed either way. A human may be viewing the page.',
        ),
        2 => 
        array (
          'Name' => 'Low',
          'Description' => 'Almost certainly no human is directly involved in viewing the page content.',
        ),
        3 => 
        array (
          'Name' => 'Unknown',
          'Description' => 'The 51Degrees JavaScript has not run on this request, so whether the browser window was in view was never measured and no confidence can be given. The first request of a session, and every request of an integration that never runs the JavaScript, answers this.',
        ),
      ),
      'IsMandatory' => false,
      'IsObsolete' => false,
      'IsPopular' => true,
      'ExportValues' => true,
      'Category' => 'Human Detection',
      'Dependencies' => 
      array (
        0 => 'device.IsCrawler',
        1 => 'device.HasWebDriver',
        2 => 'device.IsHeadless',
        3 => 'device.IsVisible',
        4 => 'device.BrowserReleaseYear',
        5 => 'device.BrowserPreviewYear',
        6 => 'device.BrowserPreviewAge',
        7 => 'device.BrowserReleaseAge',
        8 => 'ip.HumanProbability',
        9 => 'ip.ConnectionType',
      ),
    ),
    'properties' => 
    array (
      0 => 
      array (
        'key' => 'device.iscrawler',
        'name' => 'device.IsCrawler',
        'type' => 'bool',
        'elementKey' => 'device',
        'propertyName' => 'IsCrawler',
      ),
      1 => 
      array (
        'key' => 'device.haswebdriver',
        'name' => 'device.HasWebDriver',
        'type' => 'string',
        'elementKey' => 'device',
        'propertyName' => 'HasWebDriver',
      ),
      2 => 
      array (
        'key' => 'device.isheadless',
        'name' => 'device.IsHeadless',
        'type' => 'bool',
        'elementKey' => 'device',
        'propertyName' => 'IsHeadless',
      ),
      3 => 
      array (
        'key' => 'device.isvisible',
        'name' => 'device.IsVisible',
        'type' => 'string',
        'elementKey' => 'device',
        'propertyName' => 'IsVisible',
      ),
      4 => 
      array (
        'key' => 'device.browserreleaseyear',
        'name' => 'device.BrowserReleaseYear',
        'type' => 'int',
        'elementKey' => 'device',
        'propertyName' => 'BrowserReleaseYear',
      ),
      5 => 
      array (
        'key' => 'device.browserpreviewyear',
        'name' => 'device.BrowserPreviewYear',
        'type' => 'int',
        'elementKey' => 'device',
        'propertyName' => 'BrowserPreviewYear',
      ),
      6 => 
      array (
        'key' => 'device.browserpreviewage',
        'name' => 'device.BrowserPreviewAge',
        'type' => 'int',
        'elementKey' => 'device',
        'propertyName' => 'BrowserPreviewAge',
      ),
      7 => 
      array (
        'key' => 'device.browserreleaseage',
        'name' => 'device.BrowserReleaseAge',
        'type' => 'int',
        'elementKey' => 'device',
        'propertyName' => 'BrowserReleaseAge',
      ),
      8 => 
      array (
        'key' => 'ip.humanprobability',
        'name' => 'ip.HumanProbability',
        'type' => 'int',
        'elementKey' => 'ip',
        'propertyName' => 'HumanProbability',
      ),
      9 => 
      array (
        'key' => 'ip.connectiontype',
        'name' => 'ip.ConnectionType',
        'type' => 'string',
        'elementKey' => 'ip',
        'propertyName' => 'ConnectionType',
      ),
    ),
    'checks' => 
    array (
      0 => 
      array (
        'name' => 'IsCrawler',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 0,
          'propertyName' => 'device.IsCrawler',
          'op' => 'Eq',
          'operand' => true,
          'value' => true,
          'type' => 'bool',
        ),
      ),
      1 => 
      array (
        'name' => 'HasWebDriver',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 1,
          'propertyName' => 'device.HasWebDriver',
          'op' => 'Eq',
          'operand' => 'True',
          'value' => 'True',
          'type' => 'string',
        ),
      ),
      2 => 
      array (
        'name' => 'IsHeadless',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 2,
          'propertyName' => 'device.IsHeadless',
          'op' => 'Eq',
          'operand' => true,
          'value' => true,
          'type' => 'bool',
        ),
      ),
      3 => 
      array (
        'name' => 'WindowNotMeasured',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 3,
          'propertyName' => 'device.IsVisible',
          'op' => 'Eq',
          'operand' => 'Unknown',
          'value' => 'Unknown',
          'type' => 'string',
        ),
      ),
      4 => 
      array (
        'name' => 'WebDriverNotAsked',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 1,
          'propertyName' => 'device.HasWebDriver',
          'op' => 'Eq',
          'operand' => 'Unknown',
          'value' => 'Unknown',
          'type' => 'string',
        ),
      ),
      5 => 
      array (
        'name' => 'WindowInView',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 3,
          'propertyName' => 'device.IsVisible',
          'op' => 'Eq',
          'operand' => 'True',
          'value' => 'True',
          'type' => 'string',
        ),
      ),
      6 => 
      array (
        'name' => 'PreviewIsFresh',
        'condition' => 
        array (
          'kind' => 'all',
          'items' => 
          array (
            0 => 
            array (
              'kind' => 'compare',
              'slot' => 4,
              'propertyName' => 'device.BrowserReleaseYear',
              'op' => 'Le',
              'operand' => 0,
              'value' => 0,
              'type' => 'int',
            ),
            1 => 
            array (
              'kind' => 'compare',
              'slot' => 5,
              'propertyName' => 'device.BrowserPreviewYear',
              'op' => 'Gt',
              'operand' => 0,
              'value' => 0,
              'type' => 'int',
            ),
            2 => 
            array (
              'kind' => 'compare',
              'slot' => 6,
              'propertyName' => 'device.BrowserPreviewAge',
              'op' => 'Le',
              'operand' => 3,
              'value' => 3,
              'type' => 'int',
            ),
          ),
        ),
      ),
      7 => 
      array (
        'name' => 'PreviewIsCurrent',
        'condition' => 
        array (
          'kind' => 'all',
          'items' => 
          array (
            0 => 
            array (
              'kind' => 'compare',
              'slot' => 4,
              'propertyName' => 'device.BrowserReleaseYear',
              'op' => 'Le',
              'operand' => 0,
              'value' => 0,
              'type' => 'int',
            ),
            1 => 
            array (
              'kind' => 'compare',
              'slot' => 5,
              'propertyName' => 'device.BrowserPreviewYear',
              'op' => 'Gt',
              'operand' => 0,
              'value' => 0,
              'type' => 'int',
            ),
            2 => 
            array (
              'kind' => 'compare',
              'slot' => 6,
              'propertyName' => 'device.BrowserPreviewAge',
              'op' => 'Lt',
              'operand' => 24,
              'value' => 24,
              'type' => 'int',
            ),
          ),
        ),
      ),
      8 => 
      array (
        'name' => 'BrowserIsCurrent',
        'condition' => 
        array (
          'kind' => 'any',
          'items' => 
          array (
            0 => 
            array (
              'kind' => 'all',
              'items' => 
              array (
                0 => 
                array (
                  'kind' => 'compare',
                  'slot' => 4,
                  'propertyName' => 'device.BrowserReleaseYear',
                  'op' => 'Gt',
                  'operand' => 0,
                  'value' => 0,
                  'type' => 'int',
                ),
                1 => 
                array (
                  'kind' => 'compare',
                  'slot' => 7,
                  'propertyName' => 'device.BrowserReleaseAge',
                  'op' => 'Lt',
                  'operand' => 24,
                  'value' => 24,
                  'type' => 'int',
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'check',
              'name' => 'PreviewIsCurrent',
              'index' => 7,
            ),
          ),
        ),
      ),
      9 => 
      array (
        'name' => 'BrowserIsFresh',
        'condition' => 
        array (
          'kind' => 'any',
          'items' => 
          array (
            0 => 
            array (
              'kind' => 'all',
              'items' => 
              array (
                0 => 
                array (
                  'kind' => 'compare',
                  'slot' => 4,
                  'propertyName' => 'device.BrowserReleaseYear',
                  'op' => 'Gt',
                  'operand' => 0,
                  'value' => 0,
                  'type' => 'int',
                ),
                1 => 
                array (
                  'kind' => 'compare',
                  'slot' => 7,
                  'propertyName' => 'device.BrowserReleaseAge',
                  'op' => 'Le',
                  'operand' => 3,
                  'value' => 3,
                  'type' => 'int',
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'check',
              'name' => 'PreviewIsFresh',
              'index' => 6,
            ),
          ),
        ),
      ),
      10 => 
      array (
        'name' => 'AddressNotAgainstHuman',
        'condition' => 
        array (
          'kind' => 'any',
          'items' => 
          array (
            0 => 
            array (
              'kind' => 'compare',
              'slot' => 8,
              'propertyName' => 'ip.HumanProbability',
              'op' => 'Ge',
              'operand' => 7,
              'value' => 7,
              'type' => 'int',
            ),
            1 => 
            array (
              'kind' => 'compare',
              'slot' => 8,
              'propertyName' => 'ip.HumanProbability',
              'op' => 'Lt',
              'operand' => 0,
              'value' => 0,
              'type' => 'int',
            ),
          ),
        ),
      ),
      11 => 
      array (
        'name' => 'ConnectionCarriesPeople',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 9,
          'propertyName' => 'ip.ConnectionType',
          'op' => 'In',
          'operand' => 
          array (
            0 => 'Broadband',
            1 => 'Cellular',
          ),
          'value' => 
          array (
            0 => 'Broadband',
            1 => 'Cellular',
          ),
          'type' => 'string',
        ),
      ),
    ),
    'rules' => 
    array (
      0 => 
      array (
        'when' => 
        array (
          'kind' => 'check',
          'name' => 'IsCrawler',
          'index' => 0,
        ),
        'value' => 'Low',
      ),
      1 => 
      array (
        'when' => 
        array (
          'kind' => 'check',
          'name' => 'HasWebDriver',
          'index' => 1,
        ),
        'value' => 'Low',
      ),
      2 => 
      array (
        'when' => 
        array (
          'kind' => 'check',
          'name' => 'IsHeadless',
          'index' => 2,
        ),
        'value' => 'Low',
      ),
      3 => 
      array (
        'when' => 
        array (
          'kind' => 'any',
          'items' => 
          array (
            0 => 
            array (
              'kind' => 'check',
              'name' => 'WindowNotMeasured',
              'index' => 3,
            ),
            1 => 
            array (
              'kind' => 'check',
              'name' => 'WebDriverNotAsked',
              'index' => 4,
            ),
          ),
        ),
        'value' => 'Unknown',
      ),
      4 => 
      array (
        'when' => 
        array (
          'kind' => 'aggregate',
          'agg' => 'Failed',
          'group' => 
          array (
            0 => 5,
            1 => 9,
            2 => 8,
            3 => 10,
            4 => 11,
          ),
          'op' => 'Eq',
          'operand' => 0,
          'value' => 0,
        ),
        'value' => 'High',
      ),
      5 => 
      array (
        'when' => 
        array (
          'kind' => 'aggregate',
          'agg' => 'Failed',
          'group' => 
          array (
            0 => 5,
            1 => 8,
            2 => 10,
            3 => 11,
          ),
          'op' => 'Le',
          'operand' => 1,
          'value' => 1,
        ),
        'value' => 'Medium',
      ),
      6 => 
      array (
        'when' => NULL,
        'value' => 'Low',
      ),
    ),
  ),
);
