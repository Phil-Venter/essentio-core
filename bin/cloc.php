<?php

function replaceClocInReadme(string $readmePath, string $key, string $clocTable): bool
{
    if (!file_exists($readmePath)) {
        fwrite(STDERR, "Missing README file: $readmePath\n");
        return false;
    }

    if (($readme = file_get_contents($readmePath)) === false) {
        fwrite(STDERR, "Failed to read README file: $readmePath\n");
        return false;
    }

    $pattern = "/(<!--\s*$key\s*-->\s*)```.*?```(\s*<!--\s*\.\/$key\s*-->)/s";
    $replacement = '$1```' . PHP_EOL . $clocTable . PHP_EOL . "```" . '$2';

    if (!preg_match($pattern, $readme)) {
        fwrite(STDERR, "Could not locate block for key: $key\n");
        return false;
    }

    if (($updated = preg_replace($pattern, $replacement, $readme)) === null) {
        fwrite(STDERR, "Regex replacement failed for key: $key\n");
        return false;
    }

    return file_put_contents($readmePath, $updated) !== false;
}

function generateClocTable(string $file): string
{
    if (!shell_exec("which cloc")) {
        return "cloc is not installed.\n";
    }

    $file = escapeshellcmd($file);
    $output = shell_exec("cloc --fmt=2 --hide-rate --quiet '$file' 2>/dev/null");

    if (!$output) {
        return "Failed to execute cloc or no output returned.\n";
    }

    $pattern = '/^(-{30,}\nLanguage\s+files\s+blank\s+comment\s+code\s+Total\n(-{30,}\n.+?\n)-{30,})/sm';

    if (preg_match($pattern, $output, $matches)) {
        return $matches[1];
    }

    return "No table found.\n";
}

function report(string $label, bool $result): void
{
    printf("[%s] %s\n", $result ? "OK" : "FAIL", $label);
}

$readme = __DIR__ . "/../README.md";

report("cloc-base", replaceClocInReadme($readme, "cloc-base", generateClocTable(__DIR__ . "/../dist/base.php")));
report("cloc-full", replaceClocInReadme($readme, "cloc-full", generateClocTable(__DIR__ . "/../dist/full.php")));
report("cloc-src", replaceClocInReadme($readme, "cloc-src", generateClocTable(__DIR__ . "/../src")));
