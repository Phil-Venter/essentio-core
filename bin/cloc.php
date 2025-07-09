<?php

function getFiles(string $dir): array
{
    if (is_file($dir)) {
        return [$dir];
    }

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];

    foreach ($rii as $file) {
        if (!$file->isDir()) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function analyzeFile(string $filepath, array $commentSyntax): array
{
    $lines = file($filepath);

    $inMultiLine = false;
    $code = $comments = $blank = 0;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === "") {
            $blank++;
        } elseif ($inMultiLine) {
            $comments++;
            if (strpos($trimmed, $commentSyntax["multi_end"]) !== false) {
                $inMultiLine = false;
            }
        } elseif (isset($commentSyntax["multi_start"]) && strpos($trimmed, $commentSyntax["multi_start"]) !== false) {
            $comments++;
            if (strpos($trimmed, $commentSyntax["multi_end"]) === false) {
                $inMultiLine = true;
            }
        } elseif (strpos($trimmed, $commentSyntax["single"]) === 0) {
            $comments++;
        } else {
            $code++;
        }
    }

    return ["blank" => $blank, "comment" => $comments, "code" => $code];
}

function cloc(string $dir): array
{
    $extMap = [
        "php" => ["single" => "//", "multi_start" => "/*", "multi_end" => "*/"],
    ];

    $result = [];

    foreach (getFiles($dir) as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if (!isset($extMap[$ext])) {
            continue;
        }

        $stats = analyzeFile($file, $extMap[$ext]);

        if (!isset($result[$ext])) {
            $result[$ext] = ["files" => 0, "blank" => 0, "comment" => 0, "code" => 0];
        }

        $result[$ext]["files"]++;
        $result[$ext]["blank"] += $stats["blank"];
        $result[$ext]["comment"] += $stats["comment"];
        $result[$ext]["code"] += $stats["code"];
    }

    return $result;
}

function formatClocTable(array $results): string
{
    $out = [];
    $out[] = str_repeat("-", 70);
    $out[] = sprintf("%-20s%10s%10s%10s%10s%10s", "language", "files", "blank", "comment", "code", "total");
    $out[] = str_repeat("-", 70);

    foreach ($results as $lang => $stat) {
        $out[] = sprintf(
            "%-20s%10s%10s%10s%10s%10s",
            $lang,
            $stat["files"],
            $stat["blank"],
            $stat["comment"],
            $stat["code"],
            $stat["files"] + $stat["blank"] + $stat["comment"] + $stat["code"]
        );
    }

    $out[] = str_repeat("-", 70);
    return implode(PHP_EOL, $out);
}

function replaceClocInReadme(string $readmePath, string $key, string $clocTable): bool
{
    if (!file_exists($readmePath)) {
        fwrite(STDERR, "Missing README file: $readmePath\n");
        return false;
    }

    $readme = file_get_contents($readmePath);
    if ($readme === false) {
        fwrite(STDERR, "Failed to read README file: $readmePath\n");
        return false;
    }

    $pattern = "/(<!--\s*$key\s*-->\s*)```.*?```(\s*<!--\s*\.\/$key\s*-->)/s";
    $replacement = '$1```' . PHP_EOL . $clocTable . PHP_EOL . "```" . '$2';

    if (!preg_match($pattern, $readme)) {
        fwrite(STDERR, "Could not locate block for key: $key\n");
        return false;
    }

    $updated = preg_replace($pattern, $replacement, $readme);
    if ($updated === null) {
        fwrite(STDERR, "Regex replacement failed for key: $key\n");
        return false;
    }

    return file_put_contents($readmePath, $updated) !== false;
}

function report(string $label, bool $result): void
{
    printf("[%s] %s\n", $result ? "OK" : "FAIL", $label);
}

$readme = __DIR__ . "/../README.md";
report("cloc-base", replaceClocInReadme($readme, "cloc-base", formatClocTable(cloc(__DIR__ . "/../dist/base.php"))));
report("cloc-full", replaceClocInReadme($readme, "cloc-full", formatClocTable(cloc(__DIR__ . "/../dist/full.php"))));
report("cloc-src", replaceClocInReadme($readme, "cloc-src", formatClocTable(cloc(__DIR__ . "/../src"))));
