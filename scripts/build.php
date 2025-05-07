<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;

function stripNamespaceAndUse(string $filePath): string
{
    $lines = file($filePath, FILE_IGNORE_NEW_LINES);

    $filteredLines = array_filter($lines, fn($line): bool => !preg_match("/^\s*(namespace|use)\b/", (string) $line));

    return implode(PHP_EOL, $filteredLines);
}

function stripSlashesOutsideQuotes(string $code): string
{
    $tokens = token_get_all($code);
    $output = "";

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            if ($token !== "\\") {
                $output .= $token;
            }

            continue;
        }

        [$id, $text] = $token;

        if ($id === T_STRING || $id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
            $output .= $text;
            continue;
        }

        if ($id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED || $id === T_NAME_RELATIVE) {
            $output .= trim($text, "\\");
            continue;
        }

        $output .= str_replace("\\", "", $text);
    }

    return $output;
}

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

    $parsed = PhpFile::fromCode(stripNamespaceAndUse($file));
    foreach ($parsed->getClasses() as $class) {
        $outputCode .= $printer->printClass($class) . "\n\n";
    }
}

$functionsFile = $srcDir . "/functions.php";
if (file_exists($functionsFile)) {
    $parsed = PhpFile::fromCode(stripNamespaceAndUse($functionsFile));
    foreach ($parsed->getFunctions() as $function) {
        $outputCode .= $printer->printFunction($function) . "\n\n";
    }
}

file_put_contents($outputPath, str_replace("\n\n\n", "\n\n", trim(stripSlashesOutsideQuotes($outputCode))) . PHP_EOL);
