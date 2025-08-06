<?php

function scanPhpFiles($directory) {
    $directory = rtrim($directory, '/*');

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $files = [];

    foreach ($rii as $file) {
        if (!$file->isFile() || !$file->isReadable()) {
            continue;
        }

        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }

    return $files;
}

function countPhpFileStats($filename) {
    $lines = file($filename, FILE_IGNORE_NEW_LINES);

    $blank = 0;
    $comment = 0;
    $code = 0;

    $inBlockComment = false;

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '') {
            $blank++;
            continue;
        }

        if ($inBlockComment) {
            $comment++;
            if (strpos($trim, '*/') !== false) $inBlockComment = false;
            continue;
        }

        if (preg_match('/^\s*\/\//', $trim) || preg_match('/^\s*#/', $trim)) {
            $comment++;
            continue;
        }

        if (preg_match('/^\s*\/\*/', $trim)) {
            $comment++;
            if (strpos($trim, '*/') === false) $inBlockComment = true;
            continue;
        }

        $code++;
    }

    return [$code, $blank, $comment, count($lines)];
}

function printRow($row, $colWidths) {
    echo "|";
    foreach ($row as $i => $value) {
        $min = $i === 'FILE' ? '-' : '';
        printf(" %{$min}{$colWidths[$i]}s |", $value);
    }
    echo "\n";
}

$distFiles = [...glob('dist/*.php'), 'src/*' => scanPhpFiles('src/*')];

$rows = [];

foreach ($distFiles as $key => $file) {
    if (is_string($key) && is_array($file)) {
        $code = $blank = $comment = $total = 0;

        foreach ($file as $f) {
            [$_code, $_blank, $_comment, $_total] = countPhpFileStats($f);

            $code += $_code;
            $blank += $_blank;
            $comment += $_comment;
            $total += $_total;
        }
    } else {
        [$code, $blank, $comment, $total] = countPhpFileStats($file);
        $key = basename($file);
    }

    $rows['FILE'][] = $key;
    $rows['CODE'][] = (string) $code;
    $rows['BLANK'][] = (string) $blank;
    $rows['COMMENT'][] = (string) $comment;
    $rows['TOTAL'][] = (string) $total;
}

$fileLength = max(strlen('FILE'), ...array_map(fn ($val) => strlen($val), $rows['FILE']));
$codeLength = max(strlen('CODE'), ...array_map(fn ($val) => strlen($val), $rows['CODE']));
$blankLength = max(strlen('BLANK'), ...array_map(fn ($val) => strlen($val), $rows['BLANK']));
$commentLength = max(strlen('COMMENT'), ...array_map(fn ($val) => strlen($val), $rows['COMMENT']));
$totalLength = max(strlen('TOTAL'), ...array_map(fn ($val) => strlen($val), $rows['TOTAL']));

$output = "<!-- cloc -->\n";
$output .= vsprintf("| %-{$fileLength}s | %-{$codeLength}s | %-{$blankLength}s | %-{$commentLength}s | %-{$totalLength}s |\n",
    ['FILE', 'CODE', 'BLANK', 'COMMENT', 'TOTAL']);

$output .= vsprintf("| %s | %s: | %s: | %s: | %s: |\n", [
        str_repeat('-', $fileLength),
        str_repeat('-', $codeLength - 1),
        str_repeat('-', $blankLength - 1),
        str_repeat('-', $commentLength - 1),
        str_repeat('-', $totalLength - 1)
    ]);

for ($i = 0; $i < count($rows['FILE']); $i++) {
    $output .= vsprintf("| %-{$fileLength}s | %{$codeLength}s | %{$blankLength}s | %{$commentLength}s | %{$totalLength}s |\n",
        [$rows['FILE'][$i], $rows['CODE'][$i], $rows['BLANK'][$i], $rows['COMMENT'][$i], $rows['TOTAL'][$i]]);
}
$output .= "<!-- ./cloc -->";

$readme = file_get_contents(__DIR__ . '/../README.md');
$updated = preg_replace('/<!-- cloc -->(.*?)<!-- \.\/cloc -->/s', $output, $readme);
file_put_contents(__DIR__ . '/../README.md', $updated);
