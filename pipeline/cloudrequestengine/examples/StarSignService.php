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

/*
 * The star sign service that CloudEngine.php calls, run by PHP's built-in
 * web server as the example does.
 *
 *     php -S 127.0.0.1:5151 StarSignService.php
 *
 * It answers the three requests a Cloud Request Engine makes, in the shape
 * the 51Degrees cloud service answers them.
 *
 * - GET accessibleProperties gives the products and properties the
 *   resource key allows, here the product starsign with the property
 *   StarSign.
 * - GET evidencekeys gives the evidence the service reads, here the date
 *   of birth.
 * - POST <resource key>.json gives the results for the evidence in the
 *   body.
 *
 * It takes any resource key and needs nothing but PHP.
 */

// Each star sign's name, first day and last day, the days as MM-DD.
const STAR_SIGNS = [
    ['Aries', '03-21', '04-19'],
    ['Taurus', '04-20', '05-20'],
    ['Gemini', '05-21', '06-20'],
    ['Cancer', '06-21', '07-22'],
    ['Leo', '07-23', '08-22'],
    ['Virgo', '08-23', '09-22'],
    ['Libra', '09-23', '10-22'],
    ['Scorpio', '10-23', '11-21'],
    ['Sagittarius', '11-22', '12-21'],
    ['Capricorn', '12-22', '01-19'],
    ['Aquarius', '01-20', '02-18'],
    ['Pisces', '02-19', '03-20']
];

// The evidence the service reads. The Cloud Request Engine sends it in the
// body without its prefix, as dateOfBirth.
const DATE_OF_BIRTH = 'query.dateOfBirth';

header('Content-Type: application/json');
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strcasecmp($path, '/accessibleProperties') === 0) {
    echo json_encode(['Products' => ['starsign' => [
        'DataTier' => 'free',
        'Properties' => [
            ['Name' => 'StarSign', 'Type' => 'String', 'Category' => 'starsign']
        ]
    ]]]);
} elseif (strcasecmp($path, '/evidencekeys') === 0) {
    echo json_encode([DATE_OF_BIRTH]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    preg_match('~^/[^/]+\.json$~', $path) === 1) {
    parse_str((string) file_get_contents('php://input'), $fields);
    $fields = array_change_key_case($fields);
    echo json_encode([
        'starsign' => getStarSign((string) ($fields['dateofbirth'] ?? ''))
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        'errors' => ["The star sign service has nothing at '{$path}'."]
    ]);
}

/**
 * The starsign part of an answer. A value the service cannot give is null,
 * with the reason under the property's name followed by nullreason, as the
 * 51Degrees cloud service gives it.
 *
 * @return array<string, null|string>
 */
function getStarSign(string $dateOfBirth): array
{
    if ($dateOfBirth === '') {
        return noStarSign('No date of birth was sent to the star sign service.');
    }
    $date = DateTime::createFromFormat('!Y-m-d', $dateOfBirth);
    if ($date === false || $date->format('Y-m-d') !== $dateOfBirth) {
        return noStarSign(
            "The date of birth '{$dateOfBirth}' is not a date written as YYYY-MM-DD."
        );
    }
    $day = $date->format('m-d');
    foreach (STAR_SIGNS as [$name, $first, $last]) {
        // Capricorn runs from December into January, so its first day
        // comes after its last.
        $within = $first <= $last
            ? $day >= $first && $day <= $last
            : $day >= $first || $day <= $last;
        if ($within) {
            return ['starsign' => $name];
        }
    }

    return noStarSign("The service has no star sign for the day '{$day}'.");
}

/**
 * @return array<string, null|string>
 */
function noStarSign(string $reason): array
{
    return ['starsign' => null, 'starsignnullreason' => $reason];
}
