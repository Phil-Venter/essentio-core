<?php

use Essentio\Core\Argument;
use Essentio\Core\Helper;

function fakeHelper(): Helper
{
    return new Helper("");
}

it("parses command with no args", function () {
    $argv = ["cli.php", "deploy"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("deploy");
    expect($arg->get(0))->toBeNull();
    expect($arg->get("x"))->toBeNull();
});

it("parses long named args with =", function () {
    $argv = ["cli.php", "build", "--env=production", "--debug=true"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("build");
    expect($arg->get("env"))->toBe("production");
    expect($arg->get("debug"))->toBeTrue();
});

it("parses long named args with space", function () {
    $argv = ["cli.php", "run", "--port", "8080"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("run");
    expect($arg->get("port"))->toBe(8080);
});

it("parses compact short options with inline value", function () {
    $argv = ["cli.php", "serve", "-eproduction"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("serve");
    expect($arg->get("e"))->toBe("production");
});

it("parses short named args", function () {
    $argv = ["cli.php", "start", "-e", "local", "-d"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("start");
    expect($arg->get("e"))->toBe("local");
    expect($arg->get("d"))->toBeTrue();
});

it("parses positional args after command", function () {
    $argv = ["cli.php", "commit", "file1.txt", "file2.txt"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("commit");
    expect($arg->get(0))->toBe("file1.txt");
    expect($arg->get(1))->toBe("file2.txt");
});

it("parses values after -- as positional", function () {
    $argv = ["cli.php", "push", "--", "--force", "origin"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("push");
    expect($arg->get(0))->toBe("--force");
    expect($arg->get(1))->toBe("origin");
});

it("handles no command and no arguments", function () {
    $argv = ["cli.php"];

    $arg = Argument::create(fakeHelper(), $argv);

    expect($arg->command)->toBe("");
    expect($arg->get(0))->toBeNull();
});
