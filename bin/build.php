<?php

declare(strict_types=1);

use Toflar\FastSet\FastSet;
use Toflar\FastSet\SetBuilder;

require_once 'vendor/autoload.php';

SetBuilder::buildSet(__DIR__.'/../tests/Fixtures/list.txt', __DIR__.'/../var/terms_encoded.txt');

$fastSet = new FastSet(__DIR__.'/../var');
$fastSet->build(__DIR__.'/../var/terms_encoded.txt');
