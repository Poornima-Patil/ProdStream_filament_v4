<?php

// Blade directive validator
$file = '/Users/poornimapatil/Herd/ProdStream_v1.1/resources/views/filament/admin/pages/mes-work-order-dashboard.blade.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

$stack = [];
$errors = [];

foreach ($lines as $lineNum => $line) {
    $lineNum++; // 1-based line numbers

    // Find all @if directives (including @elseif)
    if (preg_match_all('/@if\s*\(/', $line, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $stack[] = ['type' => 'if', 'line' => $lineNum, 'pos' => $match[1]];
        }
    }

    // Find @else directives
    if (preg_match_all('/@else\b/', $line, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            if (empty($stack)) {
                $errors[] = "Line $lineNum: @else without matching @if";
            }
        }
    }

    // Find @elseif directives
    if (preg_match_all('/@elseif\s*\(/', $line, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            if (empty($stack)) {
                $errors[] = "Line $lineNum: @elseif without matching @if";
            }
        }
    }

    // Find @endif directives
    if (preg_match_all('/@endif\b/', $line, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            if (empty($stack)) {
                $errors[] = "Line $lineNum: @endif without matching @if";
            } else {
                array_pop($stack);
            }
        }
    }

    // Find @php directives
    if (preg_match_all('/@php\b/', $line, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $stack[] = ['type' => 'php', 'line' => $lineNum, 'pos' => $match[1]];
        }
    }

    // Find @endphp directives
    if (preg_match_all('/@endphp\b/', $line, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            if (empty($stack)) {
                $errors[] = "Line $lineNum: @endphp without matching @php";
            } else {
                $last = array_pop($stack);
                if ($last['type'] !== 'php') {
                    $errors[] = "Line $lineNum: @endphp doesn't match @php at line {$last['line']}";
                }
            }
        }
    }
}

// Check for unmatched opening directives
foreach ($stack as $item) {
    $errors[] = "Line {$item['line']}: Unmatched @{$item['type']}";
}

if (empty($errors)) {
    echo "No syntax errors found!\n";
} else {
    echo "Blade syntax errors found:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}
