<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Nette\PhpGenerator\PhpFile;

$dir = $argv[1] ?? sprintf('%s/../../dist', __DIR__);
$filepath = sprintf('%s/%s', $dir, $argv[2] ?? 'index.php');

$classes = [
    Zen\Core\Application::class    => 'Application.php',
    Zen\Core\Argument::class       => 'Argument.php',
    Zen\Core\Configuration::class  => 'Configuration.php',
    Zen\Core\Container::class      => 'Container.php',
    Zen\Core\Environment::class    => 'Environment.php',
    Zen\Core\HttpException::class  => 'HttpException.php',
    Zen\Core\Request::class        => 'Request.php',
    Zen\Core\Response::class       => 'Response.php',
    Zen\Core\Router::class         => 'Router.php',
    Zen\Core\SessionHandler::class => 'SessionHandler.php',
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
    $contents[$key] = str_replace(['\\Zen\\Core\\', "\n\n\n"], ['', "\n\n"], $content);
}

if (is_file($filepath)) {
    unlink($filepath);
}

file_put_contents($filepath, implode("\n", $contents));
