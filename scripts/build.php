<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;

$srcDir = __DIR__ . "/../src";
$distDir = $argv[1] ?? __DIR__ . "/../dist";
$outputFile = $argv[2] ?? "index.php";
$outputPath = $distDir . "/" . $outputFile;

if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

$printer = new Printer();
$outputCode = "<?php\n\n";
$files = glob($srcDir . "/*.php");

foreach ($files as $file) {
    if (basename($file) === "functions.php") {
        continue;
    }

    $parsed = PhpFile::fromCode(file_get_contents($file));
    foreach ($parsed->getClasses() as $class) {
        $outputCode .= $printer->printClass($class) . "\n\n";
    }
}

$functionsFile = $srcDir . "/functions.php";
if (file_exists($functionsFile)) {
    $parsed = PhpFile::fromCode(file_get_contents($functionsFile));
    foreach ($parsed->getFunctions() as $function) {
        $outputCode .= $printer->printFunction($function) . "\n\n";
    }
}

// UGH
$normalize = [
    "\\Essentio\\Core\\" => "",
    "\\" => "",
    "[\"']" => "[\\\"']",
    "[e.]" => "[e\\.]",
    "//+/" => "/\\/+/",
    "x00" => "\\x00",
    "*1$" => "*\\1$",
    "\n\n\n" => "\n\n",
];

$outputCode = str_replace(array_keys($normalize), array_values($normalize), $outputCode);
file_put_contents($outputPath, trim($outputCode) . PHP_EOL);
