<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Nette\PhpGenerator\PhpFile;

$dir = $argv[1] ?? sprintf("%s/../dist", __DIR__);
$filepath = sprintf("%s/%s", $dir, $argv[2] ?? "index.php");

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$contents = ["<?php\n"];

foreach (glob(sprintf("%s/../src/%s", __DIR__, "*.php")) as $file) {
    if (basename($file) === "functions.php") {
        continue;
    }

    $code = file_get_contents($file);
    $contents += PhpFile::fromCode($code)->getClasses();
}

$code = file_get_contents(sprintf("%s/../src/functions.php", __DIR__));
$contents += PhpFile::fromCode($code)->getFunctions();

foreach ($contents as $key => $content) {
    $contents[$key] = str_replace(["\\Essentio\\Core\\", "Essentio\\Core\\", "\n\n\n"], ["", "", "\n\n"], $content);
}

if (is_file($filepath)) {
    unlink($filepath);
}

file_put_contents($filepath, implode("\n", $contents));
