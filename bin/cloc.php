<?php

function replaceClocInReadme(string $readmePath, string $key, string $clocTable): bool
{
    $readme = file_get_contents($readmePath);

    if ($readme === false) {
        return false;
    }

    $pattern = "/(<!--\s*$key\s*-->\s*)```.*?```(\s*<!--\s*\.\/$key\s*-->)/s";
    $replacement = '$1```' . "\n" . $clocTable . "\n" . "```" . '$2';
    $updated = preg_replace($pattern, $replacement, $readme);

    if ($updated === null) {
        return false;
    }

    return file_put_contents($readmePath, $updated) !== false;
}

function generateClocTable(string $file): string
{
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

var_dump(replaceClocInReadme(__DIR__ . "/../README.md", "cloc-base", generateClocTable(__DIR__ . "/../dist/base.php")));
var_dump(replaceClocInReadme(__DIR__ . "/../README.md", "cloc-full", generateClocTable(__DIR__ . "/../dist/full.php")));
var_dump(replaceClocInReadme(__DIR__ . "/../README.md", "cloc-src", generateClocTable(__DIR__ . "/../src")));
