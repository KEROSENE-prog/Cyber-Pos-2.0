<?php
declare(strict_types=1);

/**
 * CyberPOS 2.0 - SimplePDF
 * Generador nativo de documentos PDF 1.4 en PHP puro (sin dependencias externas).
 * Diseñado con estética ejecutiva institucional para CyberPOS Laneros Gamer.
 */
class SimplePDF
{
    private string $orientation; // 'P' o 'L'
    private float $pageWidth;
    private float $pageHeight;
    private float $margin = 35.0;
    private float $currentY;
    private array $pages = [];
    private int $currentPage = 0;
    private string $currentStream = '';
    private string $title;

    public function __construct(string $orientation = 'L', string $title = 'Reporte Gerencial')
    {
        $this->orientation = strtoupper($orientation) === 'P' ? 'P' : 'L';
        if ($this->orientation === 'P') {
            $this->pageWidth  = 595.28;
            $this->pageHeight = 841.89;
        } else {
            $this->pageWidth  = 841.89;
            $this->pageHeight = 595.28;
        }
        $this->title = $title;
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->currentPage > 0) {
            $this->pages[$this->currentPage] = $this->currentStream;
        }
        $this->currentPage++;
        $this->currentStream = '';
        $this->currentY = $this->pageHeight - $this->margin;
    }

    private function escape(string $text): string
    {
        $clean = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
        if ($clean === false) {
            $clean = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
            if ($clean === false) {
                $clean = utf8_decode($text);
            }
        }
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $clean);
    }

    private function write(string $data): void
    {
        $this->currentStream .= $data . "\n";
    }

    public function setFillColor(float $r, float $g, float $b): void
    {
        $this->write(sprintf('%.3f %.3f %.3f rg', $r, $g, $b));
    }

    public function setStrokeColor(float $r, float $g, float $b): void
    {
        $this->write(sprintf('%.3f %.3f %.3f RG', $r, $g, $b));
    }

    public function setLineWidth(float $w): void
    {
        $this->write(sprintf('%.2f w', $w));
    }

    public function drawRect(float $x, float $y, float $w, float $h, string $op = 'f'): void
    {
        $this->write(sprintf('%.2f %.2f %.2f %.2f re %s', $x, $y - $h, $w, $h, $op));
    }

    public function drawLine(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->write(sprintf('%.2f %.2f m %.2f %.2f l S', $x1, $y1, $x2, $y2));
    }

    public function drawText(string $text, float $x, float $y, float $fontSize = 10, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $esc  = $this->escape($text);
        $this->write(sprintf('BT %s %.1f Tf %.2f %.2f Td (%s) Tj ET', $font, $fontSize, $x, $y, $esc));
    }

    public function drawHeader(
        string $title,
        string $subtitle,
        string $fecha,
        float $tasaBcv,
        string $filtros = '',
        string $responsable = ''
    ): void {
        $w = $this->pageWidth - ($this->margin * 2);
        $headerH = 68.0;

        // Barra superior decorativa verde neón
        $this->setFillColor(0.208, 0.753, 0.161); // #35c029
        $this->drawRect($this->margin, $this->currentY, $w, 4, 'f');
        $this->currentY -= 8;

        // Banner oscuro de cabecera institucional
        $this->setFillColor(0.082, 0.110, 0.137); // #151c23
        $this->drawRect($this->margin, $this->currentY, $w, $headerH, 'f');

        // Borde fino verde
        $this->setStrokeColor(0.208, 0.753, 0.161);
        $this->setLineWidth(1);
        $this->drawRect($this->margin, $this->currentY, $w, $headerH, 's');

        // Logo e Identidad Institucional
        $this->setFillColor(0.208, 0.753, 0.161);
        $this->drawText('LANEROS GAMER — CYBERPOS 2.0', $this->margin + 12, $this->currentY - 16, 12, true);

        // Título del reporte
        $this->setFillColor(1, 1, 1);
        $this->drawText($title, $this->margin + 12, $this->currentY - 30, 10.5, true);

        // Filtros aplicados
        $this->setFillColor(0.70, 0.78, 0.86);
        $filtrosText = $filtros !== '' ? "Filtros: {$filtros}" : $subtitle;
        $this->drawText($filtrosText, $this->margin + 12, $this->currentY - 44, 8, false);

        // Usuario Responsable
        $this->setFillColor(0.208, 0.753, 0.161);
        $respText = $responsable !== '' ? "Usuario Responsable: {$responsable}" : "Usuario Responsable: Administrador de Turno (administrador)";
        $this->drawText($respText, $this->margin + 12, $this->currentY - 57, 8, true);

        // Metadatos a la derecha (Fecha y Tasa BCV)
        $rightX = $this->pageWidth - $this->margin - 240;
        $this->setFillColor(0.90, 0.90, 0.90);
        $this->drawText('Emisión: ' . $fecha, $rightX, $this->currentY - 16, 8, false);

        $this->setFillColor(0.208, 0.753, 0.161);
        $this->drawText('Tasa Oficial BCV: ' . number_format($tasaBcv, 2, ',', '.') . ' Bs/$', $rightX, $this->currentY - 32, 9, true);

        $this->setFillColor(0.55, 0.65, 0.75);
        $this->drawText('Acta Gerencial y Auditoría de Sala', $rightX, $this->currentY - 46, 7.5, false);

        $this->currentY -= ($headerH + 12);
    }

    public function drawKPIs(array $kpis): void
    {
        if (empty($kpis)) {
            return;
        }

        $count = count($kpis);
        $totalW = $this->pageWidth - ($this->margin * 2);
        $gap = 10.0;
        $cardW = ($totalW - ($gap * ($count - 1))) / $count;
        $cardH = 40.0;

        $x = $this->margin;
        foreach ($kpis as $kpi) {
            $this->setFillColor(0.094, 0.125, 0.157);
            $this->drawRect($x, $this->currentY, $cardW, $cardH, 'f');

            $this->setStrokeColor(0.20, 0.28, 0.35);
            $this->setLineWidth(0.8);
            $this->drawRect($x, $this->currentY, $cardW, $cardH, 's');

            $this->setFillColor(0.208, 0.753, 0.161);
            $this->drawRect($x, $this->currentY, 3, $cardH, 'f');

            $this->setFillColor(0.65, 0.72, 0.80);
            $this->drawText((string) ($kpi['label'] ?? ''), $x + 8, $this->currentY - 14, 7, false);

            $this->setFillColor(1, 1, 1);
            $this->drawText((string) ($kpi['value'] ?? ''), $x + 8, $this->currentY - 30, 9.5, true);

            $x += $cardW + $gap;
        }

        $this->currentY -= ($cardH + 12);
    }

    public function drawRecommendation(string $text): void
    {
        if (trim($text) === '') {
            return;
        }

        $w = $this->pageWidth - ($this->margin * 2);
        $h = 28.0;

        $this->setFillColor(0.06, 0.14, 0.10);
        $this->drawRect($this->margin, $this->currentY, $w, $h, 'f');

        $this->setStrokeColor(0.208, 0.753, 0.161);
        $this->setLineWidth(1);
        $this->drawRect($this->margin, $this->currentY, $w, $h, 's');

        $this->setFillColor(0.208, 0.753, 0.161);
        $this->drawText('RECOMENDACIÓN GERENCIAL:', $this->margin + 10, $this->currentY - 12, 8, true);

        $this->setFillColor(0.90, 0.95, 0.92);
        $this->drawText($text, $this->margin + 10, $this->currentY - 22, 7.5, false);

        $this->currentY -= ($h + 10);
    }

    public function drawTable(array $headers, array $widths, array $rows, array $aligns = [], ?array $totalsRow = null): void
    {
        $tableW = array_sum($widths);
        $rowH   = 18.0;

        if ($this->currentY - ($rowH * 2) < ($this->margin + 30)) {
            $this->addPage();
        }

        // Fila de encabezado
        $this->setFillColor(0.12, 0.17, 0.22);
        $this->drawRect($this->margin, $this->currentY, $tableW, $rowH, 'f');
        $this->setStrokeColor(0.208, 0.753, 0.161);
        $this->setLineWidth(1);
        $this->drawLine($this->margin, $this->currentY - $rowH, $this->margin + $tableW, $this->currentY - $rowH);

        $curX = $this->margin;
        $this->setFillColor(0.208, 0.753, 0.161);
        foreach ($headers as $i => $h) {
            $w = $widths[$i] ?? 60.0;
            $this->drawText($h, $curX + 5, $this->currentY - 12, 8, true);
            $curX += $w;
        }

        $this->currentY -= $rowH;

        // Filas de datos
        foreach ($rows as $rowIndex => $row) {
            if ($this->currentY - $rowH < ($this->margin + 25)) {
                $this->addPage();

                $this->setFillColor(0.12, 0.17, 0.22);
                $this->drawRect($this->margin, $this->currentY, $tableW, $rowH, 'f');
                $curX = $this->margin;
                $this->setFillColor(0.208, 0.753, 0.161);
                foreach ($headers as $i => $h) {
                    $w = $widths[$i] ?? 60.0;
                    $this->drawText($h, $curX + 5, $this->currentY - 12, 8, true);
                    $curX += $w;
                }
                $this->currentY -= $rowH;
            }

            $isEven = ($rowIndex % 2 === 0);
            if ($isEven) {
                $this->setFillColor(0.06, 0.08, 0.10);
            } else {
                $this->setFillColor(0.08, 0.11, 0.14);
            }
            $this->drawRect($this->margin, $this->currentY, $tableW, $rowH, 'f');

            $this->setStrokeColor(0.16, 0.22, 0.28);
            $this->setLineWidth(0.5);
            $this->drawLine($this->margin, $this->currentY - $rowH, $this->margin + $tableW, $this->currentY - $rowH);

            $curX = $this->margin;
            foreach ($row as $colIndex => $val) {
                $colW  = $widths[$colIndex] ?? 60.0;
                $align = $aligns[$colIndex] ?? 'L';
                $valStr = (string) $val;

                if (stripos($valStr, 'CRÍTICO') !== false || stripos($valStr, 'SUBUTILIZADO') !== false) {
                    $this->setFillColor(1.0, 0.35, 0.35);
                } elseif (stripos($valStr, 'ALTO') !== false || stripos($valStr, 'ÓPTIMO') !== false || stripos($valStr, 'DIAMANTE') !== false) {
                    $this->setFillColor(0.25, 0.85, 0.35);
                } elseif (stripos($valStr, 'ALERTA') !== false || stripos($valStr, 'SOBRESTOCK') !== false) {
                    $this->setFillColor(1.0, 0.75, 0.20);
                } else {
                    $this->setFillColor(0.85, 0.88, 0.92);
                }

                $posX = $curX + 5;
                if ($align === 'R') {
                    $approxLen = mb_strlen($valStr) * 4.2;
                    $posX = max($curX + 5, $curX + $colW - $approxLen - 5);
                } elseif ($align === 'C') {
                    $approxLen = mb_strlen($valStr) * 4.2;
                    $posX = max($curX + 2, $curX + ($colW - $approxLen) / 2);
                }

                $this->drawText($valStr, $posX, $this->currentY - 12, 7.5, false);
                $curX += $colW;
            }

            $this->currentY -= $rowH;
        }

        // Fila Final de Totalización en negrita obligatoria
        if (!empty($totalsRow)) {
            if ($this->currentY - ($rowH + 4) < ($this->margin + 20)) {
                $this->addPage();
            }

            // Fondo destacado de totales
            $this->setFillColor(0.08, 0.16, 0.12);
            $this->drawRect($this->margin, $this->currentY, $tableW, $rowH + 2, 'f');

            // Bordes verde neón
            $this->setStrokeColor(0.208, 0.753, 0.161);
            $this->setLineWidth(1.2);
            $this->drawLine($this->margin, $this->currentY, $this->margin + $tableW, $this->currentY);
            $this->drawLine($this->margin, $this->currentY - ($rowH + 2), $this->margin + $tableW, $this->currentY - ($rowH + 2));

            $curX = $this->margin;
            foreach ($totalsRow as $colIndex => $val) {
                $colW   = $widths[$colIndex] ?? 60.0;
                $align  = $aligns[$colIndex] ?? 'L';
                $valStr = (string) $val;

                $this->setFillColor(0.208, 0.753, 0.161);

                $posX = $curX + 5;
                if ($align === 'R') {
                    $approxLen = mb_strlen($valStr) * 4.3;
                    $posX = max($curX + 5, $curX + $colW - $approxLen - 5);
                } elseif ($align === 'C') {
                    $approxLen = mb_strlen($valStr) * 4.3;
                    $posX = max($curX + 2, $curX + ($colW - $approxLen) / 2);
                }

                $this->drawText($valStr, $posX, $this->currentY - 13, 8, true);
                $curX += $colW;
            }

            $this->currentY -= ($rowH + 6);
        }

        $this->currentY -= 8;
    }

    public function output(): string
    {
        if ($this->currentPage > 0) {
            $this->pages[$this->currentPage] = $this->currentStream;
        }

        $totalPages = count($this->pages);

        // Agregar pie de página a cada página
        foreach ($this->pages as $pageNum => &$stream) {
            $footerY = $this->margin - 10;
            $footerW = $this->pageWidth - ($this->margin * 2);

            $footerCmds  = sprintf("\n0.180 0.240 0.300 RG 0.5 w %.2f %.2f m %.2f %.2f l S\n", $this->margin, $footerY + 14, $this->margin + $footerW, $footerY + 14);
            $footerCmds .= sprintf("BT /F1 7 Tf 0.55 0.60 0.65 rg %.2f %.2f Td (%s) Tj ET\n", $this->margin, $footerY + 4, $this->escape('CyberPOS 2.0 - Laneros Gamer | Sistema Integral de Gestión'));
            $pageStr     = sprintf('Página %d de %d', $pageNum, $totalPages);
            $footerCmds .= sprintf("BT /F2 7 Tf 0.208 0.753 0.161 rg %.2f %.2f Td (%s) Tj ET\n", $this->pageWidth - $this->margin - 75, $footerY + 4, $this->escape($pageStr));
            $stream     .= $footerCmds;
        }
        unset($stream);

        $objects = [];
        $offsets = [];

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $pageObjIds = [];
        $nextId = 5;

        for ($i = 1; $i <= $totalPages; $i++) {
            $pageId    = $nextId++;
            $contentId = $nextId++;
            $pageObjIds[] = $pageId;

            $contentStream = $this->pages[$i] ?? '';
            $streamLen     = strlen($contentStream);

            $objects[$contentId] = "<< /Length {$streamLen} >>\nstream\n{$contentStream}\nendstream";
            $objects[$pageId]    = sprintf(
                "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R >>",
                $this->pageWidth,
                $this->pageHeight,
                $contentId
            );
        }

        $kidsStr = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjIds));
        $objects[2] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", $kidsStr, $totalPages);

        ksort($objects);

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        foreach ($objects as $id => $objData) {
            $offsets[$id] = strlen($out);
            $out .= "{$id} 0 obj\n{$objData}\nendobj\n";
        }

        $xrefOffset = strlen($out);
        $maxObjId   = max(array_keys($objects));
        $out .= sprintf("xref\n0 %d\n0000000000 65535 f \n", $maxObjId + 1);

        for ($i = 1; $i <= $maxObjId; $i++) {
            if (isset($offsets[$i])) {
                $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                $out .= "0000000000 65535 f \n";
            }
        }

        $out .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF", $maxObjId + 1, $xrefOffset);
        return $out;
    }

    public function download(string $filename = 'reporte.pdf'): void
    {
        $bin = $this->output();
        if (headers_sent()) {
            echo $bin;
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $bin;
        exit;
    }
}
