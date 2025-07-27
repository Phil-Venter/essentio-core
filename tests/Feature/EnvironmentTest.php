<?php

use Essentio\Environment;

function createEnvFile(array $lines): string
{
    $file = tempnam(sys_get_temp_dir(), "env_");
    file_put_contents($file, implode(PHP_EOL, $lines));
    return $file;
}

it("returns empty environment if file does not exist", function () {
    $env = Environment::create("nonexistent.env");
    expect($env->get("ANY_KEY"))->toBeNull();
});

it("loads key-value pairs and autoCasts values", function () {
    $envFile = createEnvFile(["FOO=bar", "NUM=42", "PI=3.14", "IS_ENABLED=true", "IS_NULL=null", 'QUOTED="yes"', 'SINGLE=\'no\'']);
    $env = Environment::create($envFile);

    expect($env->get("FOO"))->toBe("bar");
    expect($env->get("NUM"))->toBe(42);
    expect($env->get("PI"))->toBe(3.14);
    expect($env->get("IS_ENABLED"))->toBeTrue();
    expect($env->get("IS_NULL"))->toBeNull();
    expect($env->get("QUOTED"))->toBe("yes");
    expect($env->get("SINGLE"))->toBe("no");

    unlink($envFile);
});

it("ignores blank lines and comments", function () {
    $envFile = createEnvFile(["", "# this is a comment", "VALID=ok", "   ", "#ANOTHER=hidden"]);
    $env = Environment::create($envFile);

    expect($env->get("VALID"))->toBe("ok");
    expect($env->get("#ANOTHER"))->toBeNull();

    unlink($envFile);
});

it("ignores malformed lines without equals sign", function () {
    $envFile = createEnvFile(["NOPE", "ANOTHER:bad", "KEY=good"]);
    $env = Environment::create($envFile);

    expect($env->get("KEY"))->toBe("good");
    expect($env->get("NOPE"))->toBeNull();
    expect($env->get("ANOTHER"))->toBeNull();

    unlink($envFile);
});
