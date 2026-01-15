<?php

declare(strict_types=1);

use Toflar\FastSet\FastSet;

require_once 'vendor/autoload.php';

$fastSet = new FastSet(__DIR__.'/../var');

$startTime = hrtime(true);
$fastSet->initialize();
echo sprintf('Initialized in %.3f ms using %.2F MiB', (hrtime(true) - $startTime) / 1_000_000, memory_get_peak_usage(true) / 1024 / 1024);
echo PHP_EOL;

$startTime = hrtime(true);
var_dump($fastSet->has('fotoausstellung'));
echo sprintf('Searched in %.3f ms using %.2F MiB', (hrtime(true) - $startTime) / 1_000_000, memory_get_peak_usage(true) / 1024 / 1024);
echo PHP_EOL;
