<?php

require_once __DIR__ . "/../src/Argument.php";

function parseFile(string $filePath): string
{
    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    $filtered = array_filter($lines, fn($line) => !preg_match("/^\s*(namespace|use)\b/", $line));
    array_shift($filtered);
    return implode(PHP_EOL, $filtered);
}

$args = \Essentio\Core\Argument::create();

$files = glob(__DIR__ . "/../src/*.php");
$args->get("extra") and ($files = array_merge($files, glob(__DIR__ . "/../src/Extra/*.php")));
$files = array_filter($files, fn($file) => basename($file) !== "functions.php");

$output = ["<?php"];
foreach ($files as $filePath) {
    $output[] = parseFile($filePath);
}
$output[] = parseFile(__DIR__ . "/../src/functions.php");
$args->get("extra") and ($output[] = parseFile(__DIR__ . "/../src/Extra/functions.php"));

file_put_contents($args->get(0), preg_replace("/\n{2,}/", "\n\n", implode(PHP_EOL, $output)) . "\n");
