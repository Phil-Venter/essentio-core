<?php

function parseFile(string $filePath): string
{
    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    $filtered = array_filter($lines, fn($line) => !preg_match("/^\s*(namespace|use)\b/", $line));
    array_shift($filtered);
    return preg_replace('/\/\*\*\n\s\*\s@api\n\s\*\//', "", implode(PHP_EOL, $filtered));
}

$argv ??= $_SERVER["argv"] ?? [];
array_shift($argv);

$outFile = array_shift($argv);
$extras = false;

if ($outFile === "--extra") {
    $outFile = array_shift($argv);
    $extras = true;
}

if (!is_dir(dirname($outFile))) {
    mkdir(dirname($outFile), 0777, true);
}

$files = glob(__DIR__ . "/../src/*.php");
$extras and ($files = array_merge($files, glob(__DIR__ . "/../src/Extra/*.php")));
$files = array_filter($files, fn($file) => basename($file) !== "functions.php");

$output = ["<?php"];

foreach ($files as $filePath) {
    if (!str_contains(strtolower($filePath), "exception")) {
        continue;
    }

    $output[] = parseFile($filePath);
}

foreach ($files as $filePath) {
    if (str_contains(strtolower($filePath), "exception")) {
        continue;
    }

    $output[] = parseFile($filePath);
}

$output[] = parseFile(__DIR__ . "/../src/functions.php");
$extras and ($output[] = parseFile(__DIR__ . "/../src/Extra/functions.php"));

file_put_contents($outFile, preg_replace("/\n{2,}/", "\n\n", implode(PHP_EOL, $output)) . "\n");
