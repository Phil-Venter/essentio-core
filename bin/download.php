<?php

$directories = [
    'app',
    'public',
    'src/Api',
    'src/Cli',
    'src/Extra',
    'src/Http/Extra',
    'src/Web',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

$shared_prefix = 'https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main';

$files = [
    "src/autoload.php"                 => "{$shared_prefix}/src/autoload.php",
    // base
    "src/Application.php"              => "{$shared_prefix}/src/Application.php",
    "src/Container.php"                => "{$shared_prefix}/src/Container.php",
    "src/Environment.php"              => "{$shared_prefix}/src/Environment.php",
    "src/FrameworkException.php"       => "{$shared_prefix}/src/FrameworkException.php",
    "src/functions.php"                => "{$shared_prefix}/src/functions.php",
    // http
    "src/Http/HttpException.php"       => "{$shared_prefix}/src/Http/HttpException.php",
    "src/Http/ValidationException.php" => "{$shared_prefix}/src/Http/ValidationException.php",
    "src/Http/Request.php"             => "{$shared_prefix}/src/Http/Request.php",
    "src/Http/Response.php"            => "{$shared_prefix}/src/Http/Response.php",
    "src/Http/Route.php"               => "{$shared_prefix}/src/Http/Route.php",
    "src/Http/Router.php"              => "{$shared_prefix}/src/Http/Router.php",
    "src/Http/functions.php"           => "{$shared_prefix}/src/Http/functions.php",
    // api
    "src/Api/Jwt.php"                  => "{$shared_prefix}/src/Api/Jwt.php",
    "src/Api/functions.php"            => "{$shared_prefix}/src/Api/functions.php",
    // web
    "src/Web/Session.php"              => "{$shared_prefix}/src/Web/Session.php",
    "src/Web/Template.php"             => "{$shared_prefix}/src/Web/Template.php",
    "src/Web/functions.php"            => "{$shared_prefix}/src/Web/functions.php",
    // cli
    "src/Cli/Argument.php"             => "{$shared_prefix}/src/Cli/Argument.php",
    "src/Cli/functions.php"            => "{$shared_prefix}/src/Cli/functions.php",
    // extras
    "src/Http/Extra/Cast.php"          => "{$shared_prefix}/src/Http/Extra/Cast.php",
    "src/Http/Extra/Validate.php"      => "{$shared_prefix}/src/Http/Extra/Validate.php",
    "src/Extra/HttpClient.php"         => "{$shared_prefix}/src/Extra/HttpClient.php",
    "src/Extra/Query.php"              => "{$shared_prefix}/src/Extra/Query.php",
    "src/Extra/functions.php"          => "{$shared_prefix}/src/Extra/functions.php",
    // stubs
    "app/PingController.php"           => "{$shared_prefix}/stubs/app-ping_controller.stub",
    "routes.php"                       => "{$shared_prefix}/stubs/routes.stub",
    "public/index.php"                 => "{$shared_prefix}/stubs/public-index.stub",
    "cli"                              => "{$shared_prefix}/stubs/cli.stub",
    "bootstrap.php"                    => "{$shared_prefix}/stubs/bootstrap.stub",
];

if (!function_exists('curl_multi_init')) {
    fwrite(STDERR, "❌ PHP curl extension is required.\n");
    exit(1);
}

$multi = curl_multi_init();
$handles = [];
$paths  = [];

foreach ($files as $path => $url) {
    echo "Downloading: $path\n";
    $fp = fopen($path, 'w');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'curl/7.68.0');
    curl_multi_add_handle($multi, $ch);
    $handles[] = $ch;
    $paths[(int)$ch] = $fp;
}

$running = null;

do {
    curl_multi_exec($multi, $running);
    curl_multi_select($multi, 1.0);
} while ($running > 0);

foreach ($handles as $ch) {
    $fp = $paths[(int)$ch];
    curl_multi_remove_handle($multi, $ch);
    curl_close($ch);
    fclose($fp);
}

curl_multi_close($multi);
