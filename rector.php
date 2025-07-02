<?php

use Rector\Set\ValueObject\SetList;

return Rector\Config\RectorConfig::configure()
    ->withPaths(paths: [__DIR__ . "/src"])
    ->withPhpSets(php82: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::CODING_STYLE,
        SetList::NAMING,
        SetList::EARLY_RETURN,
        SetList::PRIVATIZATION,
        SetList::STRICT_BOOLEANS,
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withRules([
        Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector::class,
        Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector::class,
        Rector\TypeDeclaration\Rector\Property\AddPropertyTypeDeclarationRector::class,
        Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector::class,
    ]);
