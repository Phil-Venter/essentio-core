<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\CodingStyle\Rector\Stmt\RemoveUselessAliasInUseStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\FunctionLike\RemoveDeadReturnRector;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\Class_\ReturnTypeFromStrictTernaryRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . "/src"])
    ->withPhpSets(php82: true)
    ->withTypeCoverageLevel(4)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(9)
    ->withCodingStyleLevel(8)
    ->withImportNames(removeUnusedImports: true)
    ->withRules([
        RemoveDeadReturnRector::class,
        RemoveUselessAliasInUseStatementRector::class,
        RenameForeachValueVariableToMatchMethodCallReturnTypeRector::class,
        RenamePropertyToMatchTypeRector::class,
        RenameVariableToMatchMethodCallReturnTypeRector::class,
        ReturnTypeFromStrictTernaryRector::class,
        ThrowWithPreviousExceptionRector::class,
    ]);
