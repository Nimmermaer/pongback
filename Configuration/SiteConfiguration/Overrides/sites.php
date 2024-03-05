<?php

$GLOBALS['SiteConfiguration']['site']['columns']['pingback_identifier'] = [
    'label' => 'Pingback Identifier',
    'description' => 'Identifier aus Reactions Datensatz hinterlegen',
    'config' => [
        'type' => 'input',
    ],
];

// And add it to showitem
$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] = str_replace(
    'routes,',
    'routes, --div--;Pongback, pingback_identifier, ',
    (string) $GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'],
);
