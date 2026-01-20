<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->ignoreErrorsOnExtension('ext-zlib', [ErrorType::SHADOW_DEPENDENCY]) // Optional, only used when building/using gzip encoded files
;