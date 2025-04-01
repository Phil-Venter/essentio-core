<?php

use Essentio\Core\Argument;

describe(Argument::class, function () {
    it("parses empty arguments correctly", function () {
        $arg = Argument::init([]);
        expect($arg->command)->toBe("");
        expect($arg->named)->toBe([]);
        expect($arg->positional)->toBe([]);
    });

    it("parses only command when it is provided", function () {
        $arg = Argument::init(["script.php", "list"]);
        expect($arg->command)->toBe("list");
        expect($arg->named)->toBe([]);
        expect($arg->positional)->toBe([]);
    });

    it("parses command with additional positional arguments", function () {
        $arg = Argument::init(["script.php", "run", "first", "second"]);
        expect($arg->command)->toBe("run");
        expect($arg->positional)->toBe(["first", "second"]);
        expect($arg->named)->toBe([]);
    });

    it("parses long named argument with equals syntax", function () {
        $arg = Argument::init(["script.php", "--verbose=true"]);
        expect($arg->named)->toHaveKey("verbose");
        expect($arg->named["verbose"])->toBe("true");
        expect($arg->command)->toBe("");
    });

    it("parses long named argument with separate value", function () {
        $arg = Argument::init(["script.php", "--output", "file.txt"]);
        expect($arg->named)->toHaveKey("output");
        expect($arg->named["output"])->toBe("file.txt");
    });

    it("parses long named argument without a value as a flag", function () {
        $arg = Argument::init(["script.php", "--debug"]);
        expect($arg->named)->toHaveKey("debug");
        expect($arg->named["debug"])->toBeTrue();
    });

    it("parses short named argument with an attached value", function () {
        $arg = Argument::init(["script.php", "-fvalue"]);
        expect($arg->named)->toHaveKey("f");
        expect($arg->named["f"])->toBe("value");
    });

    it("parses short named argument with separate value", function () {
        $arg = Argument::init(["script.php", "-o", "file.txt"]);
        expect($arg->named)->toHaveKey("o");
        expect($arg->named["o"])->toBe("file.txt");
    });

    it('stops parsing named arguments after encountering "--"', function () {
        $arg = Argument::init(["script.php", "--debug", "--", "--not-a-flag", "positional"]);
        expect($arg->named)->toHaveKey("debug");
        expect($arg->named["debug"])->toBeTrue();
        expect($arg->positional)->toBe(["--not-a-flag", "positional"]);
    });

    it("parses a mix of command, named and positional arguments correctly", function () {
        $argv = ["script.php", "execute", "--env=production", "arg1", "-v", "arg2"];
        $arg = Argument::init($argv);
        expect($arg->command)->toBe("execute");
        expect($arg->named)->toHaveKey("env");
        expect($arg->named["env"])->toBe("production");
        expect($arg->positional)->toBe(["arg1"]);
        expect($arg->named)->toHaveKey("v");
        expect($arg->named["v"])->toBe("arg2");
    });

    it("retrieves values using the get() method", function () {
        $arg = Argument::init(["script.php", "--mode=fast", "command", "positional"]);
        expect($arg->get("mode"))->toBe("fast");
        expect($arg->get(0))->toBe("positional");
        expect($arg->get("nonexistent", "default"))->toBe("default");
    });

    it('stops processing arguments when a short argument contains "=" in its attached value', function () {
        $argv = ["script.php", "-f=value", "command", "--option", "data"];
        $arg = Argument::init($argv);
        expect($arg->command)->toBe("");
        expect($arg->named)->toBe([]);
        expect($arg->positional)->toBe([]);
    });
});
