<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Nette\PhpGenerator\PhpFile;

$dir = $argv[1] ?? sprintf('%s/../../dist', __DIR__);
$filepath = sprintf('%s/%s', $dir, $argv[2] ?? 'index.php');

$classes = [
    Essentio\Core\Application::class    => 'Application.php',
    Essentio\Core\Argument::class       => 'Argument.php',
    Essentio\Core\Configuration::class  => 'Configuration.php',
    Essentio\Core\Container::class      => 'Container.php',
    Essentio\Core\Environment::class    => 'Environment.php',
    Essentio\Core\HttpException::class  => 'HttpException.php',
    Essentio\Core\Request::class        => 'Request.php',
    Essentio\Core\Response::class       => 'Response.php',
    Essentio\Core\Router::class         => 'Router.php',
    Essentio\Core\SessionHandler::class => 'SessionHandler.php',
];

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$contents = ["<?php\n"];

foreach ($classes as $class => $file) {
    $code = file_get_contents(sprintf('%s/../classes/%s', __DIR__, $file));
    $contents += PhpFile::fromCode($code)->getClasses();
}

$code = file_get_contents(sprintf('%s/../functions.php', __DIR__));
$contents += PhpFile::fromCode($code)->getFunctions();

foreach ($contents as $key => $content) {
    $contents[$key] = str_replace(['\\Essentio\\Core\\', "\n\n\n"], ['', "\n\n"], $content);
}

if (is_file($filepath)) {
    unlink($filepath);
}

file_put_contents($filepath, implode("\n", $contents));
