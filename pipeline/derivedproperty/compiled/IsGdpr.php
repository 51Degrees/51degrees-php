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

// Written by ci/compile-built-in-scripts.php from scripts/IsGdpr.yaml. Do not edit.

return array (
  'sha256' => '21e307a6c251751cd933c16e7c20548a23d1954e28a7cc401b069fb96ffff653',
  'script' => 
  array (
    'format' => 1,
    'name' => 'IsGdpr',
    'version' => '0.1.0',
    'deprecated' => false,
    'deprecationNote' => NULL,
    'output' => 
    array (
      'Name' => 'IsGdpr',
      'Description' => 'Whether the General Data Protection Regulation, or the United Kingdom\'s version of it, is likely to apply to the person making the request, judged from the country of the supplied location. True for the European Economic Area and the United Kingdom, and False everywhere else, including where the location could not be placed. This is a default for a caller that knows nothing about its own position, and not a determination, because the regulation also reaches an organisation by where it is established.',
      'ValueType' => 'bool',
      'IsList' => false,
      'Values' => NULL,
      'IsMandatory' => true,
      'IsObsolete' => false,
      'IsPopular' => false,
      'ExportValues' => true,
      'Category' => 'Country Information',
      'Dependencies' => 
      array (
        0 => 'ip.CountryCode',
      ),
    ),
    'properties' => 
    array (
      0 => 
      array (
        'key' => 'ip.countrycode',
        'name' => 'ip.CountryCode',
        'type' => 'string',
        'elementKey' => 'ip',
        'propertyName' => 'CountryCode',
      ),
    ),
    'checks' => 
    array (
      0 => 
      array (
        'name' => 'InScope',
        'condition' => 
        array (
          'kind' => 'compare',
          'slot' => 0,
          'propertyName' => 'ip.CountryCode',
          'op' => 'In',
          'operand' => 
          array (
            0 => 'AT',
            1 => 'BE',
            2 => 'BG',
            3 => 'HR',
            4 => 'CY',
            5 => 'CZ',
            6 => 'DK',
            7 => 'EE',
            8 => 'FI',
            9 => 'FR',
            10 => 'DE',
            11 => 'GR',
            12 => 'HU',
            13 => 'IE',
            14 => 'IT',
            15 => 'LV',
            16 => 'LT',
            17 => 'LU',
            18 => 'MT',
            19 => 'NL',
            20 => 'PL',
            21 => 'PT',
            22 => 'RO',
            23 => 'SK',
            24 => 'SI',
            25 => 'ES',
            26 => 'SE',
            27 => 'IS',
            28 => 'LI',
            29 => 'NO',
            30 => 'GB',
            31 => 'GF',
            32 => 'GP',
            33 => 'MQ',
            34 => 'YT',
            35 => 'RE',
            36 => 'MF',
          ),
          'value' => 
          array (
            0 => 'AT',
            1 => 'BE',
            2 => 'BG',
            3 => 'HR',
            4 => 'CY',
            5 => 'CZ',
            6 => 'DK',
            7 => 'EE',
            8 => 'FI',
            9 => 'FR',
            10 => 'DE',
            11 => 'GR',
            12 => 'HU',
            13 => 'IE',
            14 => 'IT',
            15 => 'LV',
            16 => 'LT',
            17 => 'LU',
            18 => 'MT',
            19 => 'NL',
            20 => 'PL',
            21 => 'PT',
            22 => 'RO',
            23 => 'SK',
            24 => 'SI',
            25 => 'ES',
            26 => 'SE',
            27 => 'IS',
            28 => 'LI',
            29 => 'NO',
            30 => 'GB',
            31 => 'GF',
            32 => 'GP',
            33 => 'MQ',
            34 => 'YT',
            35 => 'RE',
            36 => 'MF',
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
          'name' => 'InScope',
          'index' => 0,
        ),
        'value' => true,
      ),
      1 => 
      array (
        'when' => NULL,
        'value' => false,
      ),
    ),
  ),
);
