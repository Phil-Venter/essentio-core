<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\Class_\ReturnTypeFromStrictTernaryRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . "/dist", __DIR__ . "/scripts", __DIR__ . "/src"])
    ->withPhpSets(php84: true)
    ->withTypeCoverageLevel(4)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(9)
    ->withCodingStyleLevel(8)
    ->withRules([
        ReturnTypeFromStrictTernaryRector::class,
        ThrowWithPreviousExceptionRector::class,
        RenameVariableToMatchMethodCallReturnTypeRector::class,
        RenameForeachValueVariableToMatchMethodCallReturnTypeRector::class,
        RenamePropertyToMatchTypeRector::class,
        AddArrowFunctionReturnTypeRector::class,
    ]);
