<?php

use Essentio\Core\Argument;

describe(Argument::class, function (): void {
    it("parses empty arguments correctly", function (): void {
        $arg = Argument::new([]);
        expect($arg->command)->toBe("");
        expect($arg->arguments)->toBe([]);
    });

    it("parses only command when it is provided", function (): void {
        $arg = Argument::new(["script.php", "list"]);
        expect($arg->command)->toBe("list");
        expect($arg->arguments)->toBe([]);
    });

    it("parses command with additional positional arguments", function (): void {
        $arg = Argument::new(["script.php", "run", "first", "second"]);
        expect($arg->command)->toBe("run");
        expect($arg->arguments)->toBe(["first", "second"]);
    });

    it("parses long named argument with equals syntax", function (): void {
        $arg = Argument::new(["script.php", "--verbose=true"]);
        expect($arg->command)->toBe("");
        expect($arg->arguments)->toHaveKey("verbose");
        expect($arg->arguments["verbose"])->toBe("true");
    });

    it("parses long named argument with separate value", function (): void {
        $arg = Argument::new(["script.php", "--output", "file.txt"]);
        expect($arg->arguments)->toHaveKey("output");
        expect($arg->arguments["output"])->toBe("file.txt");
    });

    it("parses long named argument without a value as a flag", function (): void {
        $arg = Argument::new(["script.php", "--debug"]);
        expect($arg->arguments)->toHaveKey("debug");
        expect($arg->arguments["debug"])->toBeTrue();
    });

    it("parses short named argument with an attached value", function (): void {
        $arg = Argument::new(["script.php", "-fvalue"]);
        expect($arg->arguments)->toHaveKey("f");
        expect($arg->arguments["f"])->toBe("value");
    });

    it("parses short named argument with separate value", function (): void {
        $arg = Argument::new(["script.php", "-o", "file.txt"]);
        expect($arg->arguments)->toHaveKey("o");
        expect($arg->arguments["o"])->toBe("file.txt");
    });

    it('stops parsing named arguments after encountering "--"', function (): void {
        $arg = Argument::new(["script.php", "--debug", "--", "--not-a-flag", "positional"]);
        expect($arg->arguments)->toHaveKey("debug");
        expect($arg->arguments["debug"])->toBeTrue();
        expect($arg->arguments[0])->toBe("--not-a-flag");
        expect($arg->arguments[1])->toBe("positional");
    });

    it("parses a mix of command, named and positional arguments correctly", function (): void {
        $argv = ["script.php", "execute", "--env=production", "arg1", "-v", "arg2"];
        $arg = Argument::new($argv);
        expect($arg->command)->toBe("execute");

        expect($arg->arguments)->toHaveKey("env");
        expect($arg->arguments["env"])->toBe("production");
        expect($arg->arguments)->toHaveKey("v");
        expect($arg->arguments["v"])->toBe("arg2");
        expect($arg->arguments[0])->toBe("arg1");
    });

    it("retrieves values using the get() method", function (): void {
        $arg = Argument::new(["script.php", "--mode=fast", "command", "positional"]);
        expect($arg->get("mode"))->toBe("fast");
        expect($arg->get(0))->toBe("positional");
        expect($arg->get("nonexistent", "default"))->toBe("default");
    });

    it('stops processing arguments when a short argument contains "=" in its attached value', function (): void {
        $argv = ["script.php", "-f=value", "command", "--option", "data"];
        $arg = Argument::new($argv);
        expect($arg->command)->toBe("command");
        expect($arg->arguments)->toHaveKey("option");
        expect($arg->arguments["option"])->toBe("data");
    });
});
