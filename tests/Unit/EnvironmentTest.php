<?php

use Essentio\Core\Environment;

// describe(Environment::class, function (): void {
//     it("returns empty data when file does not exist", function (): void {
//         $env = new Environment();
//         $env->load("/non/existent/file/path");
//         expect($env->get("ANY_KEY"))->toBeNull();
//     });

//     it("parses simple unquoted values correctly", function (): void {
//         $content = <<<EOL
// APP_ENV=production
// PORT=3000
// DEBUG=true
// VERBOSE=false
// DB_NAME=null
// EOL;
//         $file = tempnam(sys_get_temp_dir(), "env");
//         file_put_contents($file, $content);

//         $env = new Environment();
//         $env->load($file);

//         expect($env->get("APP_ENV"))->toBe("production");
//         expect($env->get("PORT"))->toBe(3000);
//         expect($env->get("DEBUG"))->toBe(true);
//         expect($env->get("VERBOSE"))->toBe(false);
//         expect($env->get("DB_NAME"))->toBeNull();

//         unlink($file);
//     });

//     it("parses quoted values and preserves inner whitespace", function (): void {
//         // The quotes will be stripped, but inner whitespace remains.
//         $content = <<<EOL
// DB_HOST=" localhost "
// EOL;
//         $file = tempnam(sys_get_temp_dir(), "env");
//         file_put_contents($file, $content);

//         $env = new Environment();
//         $env->load($file);

//         expect($env->get("DB_HOST"))->toBe(" localhost ");

//         unlink($file);
//     });

//     it("skips comment and empty lines", function (): void {
//         $content = <<<EOL
// # This is a comment
// APP_ENV=development

// # Another comment
// PORT=8080
// EOL;
//         $file = tempnam(sys_get_temp_dir(), "env");
//         file_put_contents($file, $content);

//         $env = new Environment();
//         $env->load($file);

//         expect($env->get("APP_ENV"))->toBe("development");
//         expect($env->get("PORT"))->toBe(8080);
//         // Only two keys should be loaded.
//         expect($env->data)->toHaveCount(2);

//         unlink($file);
//     });

//     it("parses floating point and scientific numbers correctly", function (): void {
//         $content = <<<EOL
// RATE=1.5
// SCALE=2e3
// EOL;
//         $file = tempnam(sys_get_temp_dir(), "env");
//         file_put_contents($file, $content);

//         $env = new Environment();
//         $env->load($file);

//         expect($env->get("RATE"))->toBe(1.5);
//         expect($env->get("SCALE"))->toBe(2000.0);

//         unlink($file);
//     });

//     it("returns the default value for missing keys", function (): void {
//         $content = "KEY=value";
//         $file = tempnam(sys_get_temp_dir(), "env");
//         file_put_contents($file, $content);

//         $env = new Environment();
//         $env->load($file);

//         expect($env->get("NON_EXISTENT", "default"))->toBe("default");

//         unlink($file);
//     });
// });
