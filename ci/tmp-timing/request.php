<?php
// Times IP Intelligence against the engine built when PHP starts.
//
// "request" times a whole request: build IpIntelligenceOnPremise and a
// pipeline with no JavaScript or set-header elements, process one IP address,
// read each name once and close both.
// "reads" processes one IP address and times the reads alone, reading every
// name once per pass.
// "list" prints the names the engine was built with and their types.
//
// Arguments are the mode, the source tree, the names, the number of requests
// or passes and the IP address. Prints the time of a request, or of one read,
// in nanoseconds, and the values read.

$mode = $argv[1];
$root = $argv[2];

if ($mode === 'list') {
    $engine = ipi_engine_get();
    $required = $engine->getRequiredProperties();
    $metadata = $engine->getMetaData()->getProperties();
    $names = [];
    for ($i = 0; $i < $required->size(); ++$i) {
        $name = $required->get($i);
        $property = $metadata->getByKey($name);
        $names[] = [
            'name' => $name,
            'type' => $property === null ? null : $property->getType(),
            'available' => $property !== null && $property->getAvailable(),
        ];
    }
    echo json_encode($names), "\n";
    exit(0);
}

$names = explode(',', $argv[3]);
$count = (int) $argv[4];
$ip = $argv[5];

$prefixes = [
    'fiftyone\\pipeline\\ipintelligence\\' => "$root/ip-intelligence/src/",
    'fiftyone\\pipeline\\engines\\' => "$root/pipeline/engines/src/",
    'fiftyone\\pipeline\\core\\' => "$root/pipeline/core/src/",
];
spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});

$settings = ['addJavaScriptBuilder' => false, 'useSetHeaderProperties' => false];

$read = static function ($flowData) use ($names): array {
    $values = [];
    foreach ($names as $name) {
        $value = $flowData->ip->get(strtolower($name));
        $values[$name] = $value->hasValue ? $value->value : $value->noValueMessage;
    }

    return $values;
};

$build = static function () use ($settings, $ip): array {
    $engine = new fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise();
    $pipeline = (new fiftyone\pipeline\core\PipelineBuilder($settings))
        ->add($engine)->build();
    $flowData = $pipeline->createFlowData();
    $flowData->evidence->set('query.client-ip', $ip);
    $flowData->process();

    return [$pipeline, $flowData];
};

if ($mode === 'request') {
    $request = static function () use ($build, $read): array {
        [$pipeline, $flowData] = $build();
        $values = $read($flowData);
        $flowData->close();
        $pipeline->close();

        return $values;
    };
    $values = $request();
    for ($i = 0; $i < 100; ++$i) {
        $request();
    }
    $start = hrtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $request();
    }
    $ns = (hrtime(true) - $start) / $count;
} else {
    [$pipeline, $flowData] = $build();
    $values = $read($flowData);
    for ($i = 0; $i < 1000; ++$i) {
        $read($flowData);
    }
    $start = hrtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $read($flowData);
    }
    $ns = (hrtime(true) - $start) / ($count * count($names));
    $flowData->close();
    $pipeline->close();
}

echo json_encode(['ns' => $ns, 'values' => $values]), "\n";
