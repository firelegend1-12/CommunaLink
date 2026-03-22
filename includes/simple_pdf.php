<?php
/**
 * Minimal PDF generator for plain-text document exports.
 * This is dependency-free and suitable for lightweight server-side PDFs.
 */

function simple_pdf_escape_text($text) {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    return $text;
}

function simple_pdf_to_winansi($text) {
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            return $converted;
        }
    }
    return $text;
}

function output_simple_text_pdf($filename, $title, array $lines) {
    $title = simple_pdf_to_winansi($title);

    $safe_lines = [];
    foreach ($lines as $line) {
        $safe_lines[] = simple_pdf_to_winansi((string) $line);
    }

    $content = "BT /F1 18 Tf 50 780 Td (" . simple_pdf_escape_text($title) . ") Tj\n";
    $content .= "BT /F1 11 Tf 50 750 Td\n";

    $line_gap = 16;
    foreach ($safe_lines as $index => $line) {
        $escaped = simple_pdf_escape_text($line);
        if ($index === 0) {
            $content .= "(" . $escaped . ") Tj\n";
        } else {
            $content .= "0 -" . $line_gap . " Td (" . $escaped . ") Tj\n";
        }
    }
    $content .= "ET\n";

    $objects = [];

    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xref_pos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref_pos . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;
    exit;
}
