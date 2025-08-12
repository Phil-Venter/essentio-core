<?php

return Rector\Config\RectorConfig::configure()
    ->withPaths(paths: [__DIR__ . "/src"])
    ->withPhpSets(php82: true)
    ->withSets([
        Rector\Set\ValueObject\SetList::PHP_82,
        Rector\Set\ValueObject\SetList::CODE_QUALITY,
        Rector\Set\ValueObject\SetList::DEAD_CODE,
        Rector\Set\ValueObject\SetList::TYPE_DECLARATION,
        Rector\Set\ValueObject\SetList::CODING_STYLE,
        Rector\Set\ValueObject\SetList::NAMING,
        Rector\Set\ValueObject\SetList::EARLY_RETURN,
        Rector\Set\ValueObject\SetList::PRIVATIZATION,
        Rector\Set\ValueObject\SetList::STRICT_BOOLEANS,
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withRules([
        Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector::class,
        Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector::class,
        Rector\TypeDeclaration\Rector\Property\AddPropertyTypeDeclarationRector::class,
        Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector::class,
    ])
    ->withSkip([
        Rector\CodeQuality\Rector\FuncCall\InlineIsAInstanceOfRector::class,
        Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector::class,
    ]);
