<?php
/**
 * ChordPro PDF Generator
 *
 * Generates PDF chord charts from ChordPro parsed data
 * Uses TCPDF library for PDF generation
 */

require_once __DIR__ . '/ChordProParser.php';

class ChordProPdfGenerator
{
    private ChordProParser $parser;
    private array $options = [
        'pageSize' => 'LETTER',      // LETTER, A4, etc.
        'orientation' => 'P',         // P = Portrait, L = Landscape
        'fontSize' => 12,
        'chordSize' => 10,
        'fontFamily' => 'courier',
        'titleSize' => 18,
        'headerSize' => 10,
        'margin' => 15,
        'lineSpacing' => 1.3,
        'showChords' => true,
        'twoColumn' => false,
        'chordColor' => [0, 100, 200],  // RGB blue
    ];

    public function __construct(ChordProParser $parser, array $options = [])
    {
        $this->parser = $parser;
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Generate PDF and return as string
     */
    public function generate(): string
    {
        // Check if TCPDF is available
        $tcpdfPath = __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
        if (!file_exists($tcpdfPath)) {
            // Fall back to simple HTML-to-PDF approach
            return $this->generateSimplePdf();
        }

        require_once $tcpdfPath;

        $pdf = new TCPDF(
            $this->options['orientation'],
            'mm',
            $this->options['pageSize'],
            true,
            'UTF-8',
            false
        );

        // Set document information
        $metadata = $this->parser->getMetadata();
        $pdf->SetCreator('Alive Church Services');
        $pdf->SetAuthor($metadata['artist'] ?? 'Unknown');
        $pdf->SetTitle($metadata['title'] ?? 'Chord Chart');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $margin = $this->options['margin'];
        $pdf->SetMargins($margin, $margin, $margin);
        $pdf->SetAutoPageBreak(true, $margin);

        // Add page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont($this->options['fontFamily'], '', $this->options['fontSize']);

        // Render title
        if (isset($metadata['title'])) {
            $pdf->SetFont($this->options['fontFamily'], 'B', $this->options['titleSize']);
            $pdf->Cell(0, 10, $metadata['title'], 0, 1, 'C');
        }

        // Render metadata line
        $metaParts = [];
        if (isset($metadata['artist'])) {
            $metaParts[] = $metadata['artist'];
        }
        if (isset($metadata['key'])) {
            $metaParts[] = 'Key: ' . $metadata['key'];
        }
        if (isset($metadata['tempo'])) {
            $metaParts[] = $metadata['tempo'] . ' BPM';
        }
        if (isset($metadata['time'])) {
            $metaParts[] = $metadata['time'];
        }
        if (isset($metadata['capo'])) {
            $metaParts[] = 'Capo ' . $metadata['capo'];
        }

        if (!empty($metaParts)) {
            $pdf->SetFont($this->options['fontFamily'], '', $this->options['headerSize']);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 6, implode(' • ', $metaParts), 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(5);

        // Render sections
        $sections = $this->parser->getSections();

        if ($this->options['twoColumn']) {
            $this->renderTwoColumn($pdf, $sections);
        } else {
            $this->renderSingleColumn($pdf, $sections);
        }

        // Footer with copyright
        if (isset($metadata['copyright']) || isset($metadata['ccli'])) {
            $pdf->Ln(10);
            $pdf->SetFont($this->options['fontFamily'], '', 8);
            $pdf->SetTextColor(150, 150, 150);

            if (isset($metadata['copyright'])) {
                $pdf->Cell(0, 4, $metadata['copyright'], 0, 1, 'C');
            }
            if (isset($metadata['ccli'])) {
                $pdf->Cell(0, 4, 'CCLI Song # ' . $metadata['ccli'], 0, 1, 'C');
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Render sections in single column layout
     */
    private function renderSingleColumn($pdf, array $sections): void
    {
        foreach ($sections as $section) {
            // Section header
            $sectionLabel = ucfirst($section['type']);
            if ($section['name']) {
                $sectionLabel .= ' ' . $section['name'];
            }

            $pdf->SetFont($this->options['fontFamily'], 'B', $this->options['headerSize']);

            // Color section headers by type
            switch ($section['type']) {
                case 'chorus':
                    $pdf->SetTextColor(0, 100, 200);
                    break;
                case 'bridge':
                    $pdf->SetTextColor(40, 167, 69);
                    break;
                default:
                    $pdf->SetTextColor(80, 80, 80);
            }

            $pdf->Cell(0, 6, strtoupper($sectionLabel), 0, 1);
            $pdf->SetTextColor(0, 0, 0);

            // Render lines
            foreach ($section['lines'] as $line) {
                $this->renderLine($pdf, $line);
            }

            $pdf->Ln(4);
        }
    }

    /**
     * Render sections in two column layout
     */
    private function renderTwoColumn($pdf, array $sections): void
    {
        $pageWidth = $pdf->getPageWidth() - (2 * $this->options['margin']);
        $colWidth = ($pageWidth / 2) - 5;

        $pdf->SetFont($this->options['fontFamily'], '', $this->options['fontSize']);

        // Simple approach: split sections between columns
        $midPoint = ceil(count($sections) / 2);

        $leftSections = array_slice($sections, 0, $midPoint);
        $rightSections = array_slice($sections, $midPoint);

        $startY = $pdf->GetY();

        // Left column
        $pdf->SetXY($this->options['margin'], $startY);
        foreach ($leftSections as $section) {
            $this->renderSectionInColumn($pdf, $section, $colWidth);
        }

        $leftEndY = $pdf->GetY();

        // Right column
        $pdf->SetXY($this->options['margin'] + $colWidth + 10, $startY);
        foreach ($rightSections as $section) {
            $this->renderSectionInColumn($pdf, $section, $colWidth);
        }

        // Move to bottom of both columns
        $pdf->SetY(max($leftEndY, $pdf->GetY()));
    }

    /**
     * Render a section within a column
     */
    private function renderSectionInColumn($pdf, array $section, float $colWidth): void
    {
        $currentX = $pdf->GetX();

        // Section header
        $sectionLabel = ucfirst($section['type']);
        if ($section['name']) {
            $sectionLabel .= ' ' . $section['name'];
        }

        $pdf->SetFont($this->options['fontFamily'], 'B', $this->options['headerSize'] - 1);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($colWidth, 5, strtoupper($sectionLabel), 0, 1);
        $pdf->SetX($currentX);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont($this->options['fontFamily'], '', $this->options['fontSize'] - 1);

        foreach ($section['lines'] as $line) {
            $this->renderLineInColumn($pdf, $line, $colWidth, $currentX);
        }

        $pdf->Ln(3);
        $pdf->SetX($currentX);
    }

    /**
     * Render a single line with chords above lyrics
     */
    private function renderLine($pdf, array $line): void
    {
        if ($line['type'] === 'empty') {
            $pdf->Ln(3);
            return;
        }

        if ($line['type'] === 'comment') {
            $pdf->SetFont($this->options['fontFamily'], 'I', $this->options['fontSize'] - 1);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, $line['text'], 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            return;
        }

        if ($line['type'] !== 'line' || !isset($line['segments'])) {
            return;
        }

        $segments = $line['segments'];

        if ($this->options['showChords']) {
            // Build chord line and lyric line
            $chordLine = '';
            $lyricLine = '';

            foreach ($segments as $segment) {
                $chord = $segment['chord'] ?? '';
                $text = $segment['text'] ?? '';

                // Pad chord to match lyric length, or vice versa
                $chordLen = strlen($chord);
                $textLen = strlen($text);

                if ($chordLen > $textLen) {
                    $text .= str_repeat(' ', $chordLen - $textLen);
                } else {
                    $chord .= str_repeat(' ', $textLen - $chordLen);
                }

                $chordLine .= $chord;
                $lyricLine .= $text;
            }

            // Print chord line
            if (trim($chordLine)) {
                $pdf->SetFont($this->options['fontFamily'], 'B', $this->options['chordSize']);
                $pdf->SetTextColor(...$this->options['chordColor']);
                $pdf->Cell(0, 4, $chordLine, 0, 1);
            }

            // Print lyric line
            $pdf->SetFont($this->options['fontFamily'], '', $this->options['fontSize']);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 5, $lyricLine, 0, 1);
        } else {
            // Lyrics only
            $lyricLine = '';
            foreach ($segments as $segment) {
                $lyricLine .= $segment['text'] ?? '';
            }
            $pdf->SetFont($this->options['fontFamily'], '', $this->options['fontSize']);
            $pdf->Cell(0, 5, $lyricLine, 0, 1);
        }
    }

    /**
     * Render a line in column mode
     */
    private function renderLineInColumn($pdf, array $line, float $colWidth, float $startX): void
    {
        if ($line['type'] === 'empty') {
            $pdf->Ln(2);
            $pdf->SetX($startX);
            return;
        }

        if ($line['type'] === 'comment') {
            $pdf->SetFont($this->options['fontFamily'], 'I', $this->options['fontSize'] - 2);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($colWidth, 4, $line['text'], 0, 1);
            $pdf->SetX($startX);
            $pdf->SetTextColor(0, 0, 0);
            return;
        }

        if ($line['type'] !== 'line' || !isset($line['segments'])) {
            return;
        }

        $segments = $line['segments'];

        if ($this->options['showChords']) {
            $chordLine = '';
            $lyricLine = '';

            foreach ($segments as $segment) {
                $chord = $segment['chord'] ?? '';
                $text = $segment['text'] ?? '';

                $chordLen = strlen($chord);
                $textLen = strlen($text);

                if ($chordLen > $textLen) {
                    $text .= str_repeat(' ', $chordLen - $textLen);
                } else {
                    $chord .= str_repeat(' ', $textLen - $chordLen);
                }

                $chordLine .= $chord;
                $lyricLine .= $text;
            }

            if (trim($chordLine)) {
                $pdf->SetFont($this->options['fontFamily'], 'B', $this->options['chordSize'] - 1);
                $pdf->SetTextColor(...$this->options['chordColor']);
                $pdf->Cell($colWidth, 3, $chordLine, 0, 1);
                $pdf->SetX($startX);
            }

            $pdf->SetFont($this->options['fontFamily'], '', $this->options['fontSize'] - 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($colWidth, 4, $lyricLine, 0, 1);
            $pdf->SetX($startX);
        } else {
            $lyricLine = '';
            foreach ($segments as $segment) {
                $lyricLine .= $segment['text'] ?? '';
            }
            $pdf->SetFont($this->options['fontFamily'], '', $this->options['fontSize'] - 1);
            $pdf->Cell($colWidth, 4, $lyricLine, 0, 1);
            $pdf->SetX($startX);
        }
    }

    /**
     * Generate simple PDF without TCPDF (HTML-based fallback)
     */
    private function generateSimplePdf(): string
    {
        // Return HTML that can be converted to PDF by browser print
        $html = $this->generatePrintableHtml();

        // For now, return the HTML - can be enhanced with a lightweight PDF library
        return $html;
    }

    /**
     * Generate printable HTML (for browser print to PDF)
     */
    public function generatePrintableHtml(): string
    {
        $metadata = $this->parser->getMetadata();
        $sections = $this->parser->getSections();

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($metadata['title'] ?? 'Chord Chart') . '</title>
    <style>
        @page {
            size: letter portrait;
            margin: 0.75in;
        }

        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 12pt;
            line-height: 1.3;
            margin: 0;
            padding: 20px;
        }

        .song-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }

        .song-meta {
            text-align: center;
            color: #666;
            font-size: 10pt;
            margin-bottom: 20px;
        }

        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .section-header {
            font-weight: bold;
            font-size: 10pt;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .section-chorus .section-header {
            color: #0066cc;
        }

        .section-bridge .section-header {
            color: #28a745;
        }

        .line {
            margin-bottom: 2px;
        }

        .line.empty {
            height: 0.5em;
        }

        .chord-row {
            color: #0066cc;
            font-weight: bold;
            font-size: 10pt;
            white-space: pre;
            min-height: 1em;
        }

        .lyric-row {
            white-space: pre;
        }

        .comment {
            font-style: italic;
            color: #666;
            margin: 5px 0;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }

        @media print {
            body {
                padding: 0;
            }

            .chord-row {
                color: #000 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>';

        // Title
        if (isset($metadata['title'])) {
            $html .= '<div class="song-title">' . htmlspecialchars($metadata['title']) . '</div>';
        }

        // Meta line
        $metaParts = [];
        if (isset($metadata['artist'])) $metaParts[] = htmlspecialchars($metadata['artist']);
        if (isset($metadata['key'])) $metaParts[] = 'Key: ' . htmlspecialchars($metadata['key']);
        if (isset($metadata['tempo'])) $metaParts[] = htmlspecialchars($metadata['tempo']) . ' BPM';
        if (isset($metadata['time'])) $metaParts[] = htmlspecialchars($metadata['time']);
        if (isset($metadata['capo'])) $metaParts[] = 'Capo ' . htmlspecialchars($metadata['capo']);

        if (!empty($metaParts)) {
            $html .= '<div class="song-meta">' . implode(' &bull; ', $metaParts) . '</div>';
        }

        // Sections
        foreach ($sections as $section) {
            $html .= '<div class="section section-' . htmlspecialchars($section['type']) . '">';

            $sectionLabel = ucfirst($section['type']);
            if ($section['name']) {
                $sectionLabel .= ' ' . $section['name'];
            }
            $html .= '<div class="section-header">' . htmlspecialchars($sectionLabel) . '</div>';

            foreach ($section['lines'] as $line) {
                if ($line['type'] === 'empty') {
                    $html .= '<div class="line empty"></div>';
                } elseif ($line['type'] === 'comment') {
                    $html .= '<div class="comment">' . htmlspecialchars($line['text']) . '</div>';
                } elseif ($line['type'] === 'line' && isset($line['segments'])) {
                    $chordLine = '';
                    $lyricLine = '';

                    foreach ($line['segments'] as $segment) {
                        $chord = $segment['chord'] ?? '';
                        $text = $segment['text'] ?? '';

                        $chordLen = strlen($chord);
                        $textLen = strlen($text);

                        if ($chordLen > $textLen) {
                            $text .= str_repeat(' ', $chordLen - $textLen);
                        } else {
                            $chord .= str_repeat(' ', $textLen - $chordLen);
                        }

                        $chordLine .= $chord;
                        $lyricLine .= $text;
                    }

                    $html .= '<div class="line">';
                    if ($this->options['showChords'] && trim($chordLine)) {
                        $html .= '<div class="chord-row">' . htmlspecialchars($chordLine) . '</div>';
                    }
                    $html .= '<div class="lyric-row">' . htmlspecialchars($lyricLine) . '</div>';
                    $html .= '</div>';
                }
            }

            $html .= '</div>';
        }

        // Footer
        $footerParts = [];
        if (isset($metadata['copyright'])) {
            $footerParts[] = htmlspecialchars($metadata['copyright']);
        }
        if (isset($metadata['ccli'])) {
            $footerParts[] = 'CCLI Song # ' . htmlspecialchars($metadata['ccli']);
        }

        if (!empty($footerParts)) {
            $html .= '<div class="footer">' . implode('<br>', $footerParts) . '</div>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Output PDF directly to browser
     */
    public function output(string $filename = 'chord-chart.pdf'): void
    {
        $content = $this->generate();

        // If TCPDF isn't available, output HTML with print dialog
        if (strpos($content, '<!DOCTYPE html>') === 0) {
            header('Content-Type: text/html; charset=utf-8');
            echo $content;
            echo '<script>window.print();</script>';
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    /**
     * Save PDF to file
     */
    public function save(string $filepath): bool
    {
        $content = $this->generate();
        return file_put_contents($filepath, $content) !== false;
    }
}
