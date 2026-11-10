<?php
declare(strict_types=1);

/**
 * CyberPOS 2.0 - SimpleXLSXGen
 * Generador nativo OpenXML (.xlsx) en PHP puro (sin Composer).
 * Empaqueta hojas de cálculo válidas para Microsoft Excel, LibreOffice y Google Sheets.
 */
class SimpleXLSXGen
{
    private string $title;
    private array $headers = [];
    private array $rows = [];
    private array $types = [];
    private array $kpis = [];
    private ?array $totals = null;
    private string $metaInfo = '';

    public function __construct(string $title = 'Reporte')
    {
        $this->title = $title;
    }

    public function setMeta(string $info): self
    {
        $this->metaInfo = $info;
        return $this;
    }

    public function setKPIs(array $kpis): self
    {
        $this->kpis = $kpis;
        return $this;
    }

    public function setHeaders(array $headers, array $types = []): self
    {
        $this->headers = $headers;
        $this->types   = $types;
        return $this;
    }

    public function addRow(array $row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    public function setRows(array $rows): self
    {
        $this->rows = $rows;
        return $this;
    }

    public function setTotals(array $totals): self
    {
        $this->totals = $totals;
        return $this;
    }

    private function colLetter(int $colIndex): string
    {
        $colIndex++;
        $letter = '';
        while ($colIndex > 0) {
            $modulo = ($colIndex - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $colIndex = (int) (($colIndex - $modulo) / 26);
        }
        return $letter;
    }

    private function escapeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildSheetXml(): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";

        // Ajuste de ancho de columnas
        $colCount = max(count($this->headers), 1);
        $xml .= '  <cols>' . "\n";
        for ($c = 1; $c <= $colCount; $c++) {
            $xml .= sprintf('    <col min="%d" max="%d" width="22" customWidth="1"/>' . "\n", $c, $c);
        }
        $xml .= '  </cols>' . "\n";

        $xml .= '  <sheetData>' . "\n";
        $r = 1;

        // Fila 1: Título Institucional
        $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
        $xml .= sprintf('      <c r="A%d" s="1" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $r, $this->escapeXml('CYBERPOS 2.0 - LANEROS GAMER'));
        $xml .= '    </row>' . "\n";
        $r++;

        // Fila 2: Título del Reporte
        $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
        $xml .= sprintf('      <c r="A%d" s="2" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $r, $this->escapeXml($this->title));
        $xml .= '    </row>' . "\n";
        $r++;

        // Fila 3: Metadatos
        if ($this->metaInfo !== '') {
            $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
            $xml .= sprintf('      <c r="A%d" s="0" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $r, $this->escapeXml($this->metaInfo));
            $xml .= '    </row>' . "\n";
            $r++;
        }

        // Fila de KPIs si existen
        if (!empty($this->kpis)) {
            $r++; // espacio en blanco
            $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
            foreach ($this->kpis as $ki => $kpi) {
                $col = $this->colLetter($ki);
                $lbl = ($kpi['label'] ?? '') . ': ' . ($kpi['value'] ?? '');
                $xml .= sprintf('      <c r="%s%d" s="4" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $col, $r, $this->escapeXml($lbl));
            }
            $xml .= '    </row>' . "\n";
            $r++;
        }

        $r++; // espacio antes de la tabla

        // Encabezados de tabla
        if (!empty($this->headers)) {
            $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, count($this->headers));
            foreach ($this->headers as $ci => $h) {
                $col = $this->colLetter($ci);
                $xml .= sprintf('      <c r="%s%d" s="3" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $col, $r, $this->escapeXml((string) $h));
            }
            $xml .= '    </row>' . "\n";
            $r++;
        }

        // Filas de datos
        foreach ($this->rows as $row) {
            $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, count($row));
            foreach ($row as $ci => $val) {
                $col = $this->colLetter($ci);
                $type = $this->types[$ci] ?? 'string';

                if (is_numeric($val) && ($type === 'number' || $type === 'currency' || $type === 'percent' || $type === 'int')) {
                    $styleId = 0;
                    if ($type === 'currency')  $styleId = 5;
                    elseif ($type === 'percent') $styleId = 6;
                    elseif ($type === 'int')     $styleId = 7;
                    else                         $styleId = 8;

                    $xml .= sprintf('      <c r="%s%d" s="%d"><v>%s</v></c>' . "\n", $col, $r, $styleId, (string) $val);
                } else {
                    $xml .= sprintf('      <c r="%s%d" s="0" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $col, $r, $this->escapeXml((string) $val));
                }
            }
            $xml .= '    </row>' . "\n";
            $r++;
        }

        // Fila de Totales si existe
        if (!empty($this->totals)) {
            $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, count($this->totals));
            foreach ($this->totals as $ci => $val) {
                $col = $this->colLetter($ci);
                $type = $this->types[$ci] ?? 'string';

                if (is_numeric($val) && ($type === 'number' || $type === 'currency' || $type === 'percent' || $type === 'int')) {
                    $styleId = 9; // Total moneda/número bold
                    $xml .= sprintf('      <c r="%s%d" s="%d"><v>%s</v></c>' . "\n", $col, $r, $styleId, (string) $val);
                } else {
                    $xml .= sprintf('      <c r="%s%d" s="2" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $col, $r, $this->escapeXml((string) $val));
                }
            }
            $xml .= '    </row>' . "\n";
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';

        return $xml;
    }

    private function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n" .
'  <numFmts count="4">' . "\n" .
'    <numFmt numFmtId="164" formatCode="&quot;$&quot;#,##0.00"/>' . "\n" .
'    <numFmt numFmtId="165" formatCode="0.0%"/>' . "\n" .
'    <numFmt numFmtId="166" formatCode="#,##0"/>' . "\n" .
'    <numFmt numFmtId="167" formatCode="#,##0.00"/>' . "\n" .
'  </numFmts>' . "\n" .
'  <fonts count="4">' . "\n" .
'    <font><name val="Segoe UI"/><sz val="10"/><color rgb="FF1F2937"/></font>' . "\n" . // 0 regular
'    <font><b/><name val="Segoe UI"/><sz val="13"/><color rgb="FF35C029"/></font>' . "\n" . // 1 title neon
'    <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FF111827"/></font>' . "\n" . // 2 bold
'    <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FFFFFFFF"/></font>' . "\n" . // 3 header white
'  </fonts>' . "\n" .
'  <fills count="4">' . "\n" .
'    <fill><patternFill patternType="none"/></fill>' . "\n" .
'    <fill><patternFill patternType="gray125"/></fill>' . "\n" .
'    <fill><patternFill patternType="solid"><fgColor rgb="FF1A3822"/></patternFill></fill>' . "\n" . // 2 dark green header
'    <fill><patternFill patternType="solid"><fgColor rgb="FFE5F5E0"/></patternFill></fill>' . "\n" . // 3 kpi light green
'  </fills>' . "\n" .
'  <borders count="2">' . "\n" .
'    <border><left/><right/><top/><bottom/><diagonal/></border>' . "\n" .
'    <border>' . "\n" .
'      <left style="thin"><color rgb="FFD1D5DB"/></left>' . "\n" .
'      <right style="thin"><color rgb="FFD1D5DB"/></right>' . "\n" .
'      <top style="thin"><color rgb="FFD1D5DB"/></top>' . "\n" .
'      <bottom style="thin"><color rgb="FFD1D5DB"/></bottom>' . "\n" .
'    </border>' . "\n" .
'  </borders>' . "\n" .
'  <cellXfs count="10">' . "\n" .
'    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/>' . "\n" . // 0 regular text
'    <xf numFmtId="0" fontId="1" fillId="0" borderId="0"/>' . "\n" . // 1 banner title
'    <xf numFmtId="0" fontId="2" fillId="0" borderId="1" applyBorder="1"/>' . "\n" . // 2 bold text
'    <xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyFill="1" applyBorder="1"/>' . "\n" . // 3 header table
'    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" applyFill="1" applyBorder="1"/>' . "\n" . // 4 KPI box
'    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" . // 5 currency
'    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" . // 6 percent
'    <xf numFmtId="166" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" . // 7 int
'    <xf numFmtId="167" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" . // 8 number 2dec
'    <xf numFmtId="164" fontId="2" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" . // 9 total bold currency
'  </cellXfs>' . "\n" .
'</styleSheet>';
    }

    private function packZip(array $files): string
    {
        // Empaquetador ZIP binario nativo en memoria (RFC 1951 / DEFLATE)
        $dataEntries = '';
        $dirEntries  = '';
        $offset      = 0;

        foreach ($files as $name => $content) {
            $uncompressedSize = strlen($content);
            $crc = crc32($content);

            // Intentar compresión deflate si está disponible
            if (function_exists('gzdeflate')) {
                $compressed = gzdeflate($content);
                $compressionMethod = 8;
                $compressedSize = strlen($compressed);
            } else {
                $compressed = $content;
                $compressionMethod = 0;
                $compressedSize = $uncompressedSize;
            }

            $nameLen = strlen($name);

            // Local File Header
            $local = pack(
                'VvvvVVVVvv',
                0x04034b50, // Firma PK\x03\x04
                20,         // Versión necesaria (2.0)
                0,          // Banderas de propósito general
                $compressionMethod,
                0x4821,     // Hora DOS (10:00:00)
                0x5729,     // Fecha DOS (2025-09-09)
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0           // Extra length
            ) . $name . $compressed;

            $dataEntries .= $local;

            // Central Directory File Header
            $dir = pack(
                'VvvvvVVVVvvvvvVV',
                0x02014b50, // Firma PK\x01\x02
                20,         // Versión hecha por
                20,         // Versión necesaria
                0,          // Banderas
                $compressionMethod,
                0x4821,     // Hora DOS
                0x5729,     // Fecha DOS
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0,          // Extra length
                0,          // Comentario length
                0,          // Disk start
                0,          // Atributos internos
                32,         // Atributos externos
                $offset     // Offset del local header
            ) . $name;

            $dirEntries .= $dir;
            $offset += strlen($local);
        }

        $centralDirSize = strlen($dirEntries);
        $totalEntries   = count($files);

        // End of Central Directory Record
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,     // Firma PK\x05\x06
            0,              // Disk number
            0,              // Disk with central dir
            $totalEntries,  // Total entries on disk
            $totalEntries,  // Total entries
            $centralDirSize,// Central dir size
            $offset,        // Offset of central dir
            0               // Comment length
        );

        return $dataEntries . $dirEntries . $eocd;
    }

    public function output(): string
    {
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n" .
'  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n" .
'  <Default Extension="xml" ContentType="application/xml"/>' . "\n" .
'  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n" .
'  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n" .
'  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n" .
'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n" .
'  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n" .
'</Relationships>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n" .
'  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\n" .
'  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n" .
'</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n" .
'  <sheets>' . "\n" .
'    <sheet name="Reporte" sheetId="1" r:id="rId1"/>' . "\n" .
'  </sheets>' . "\n" .
'</workbook>';

        $files = [
            '[Content_Types].xml'        => $contentTypes,
            '_rels/.rels'                => $rels,
            'xl/_rels/workbook.xml.rels' => $wbRels,
            'xl/workbook.xml'            => $workbook,
            'xl/styles.xml'              => $this->buildStylesXml(),
            'xl/worksheets/sheet1.xml'   => $this->buildSheetXml(),
        ];

        return $this->packZip($files);
    }

    public function download(string $filename): void
    {
        $xlsx = $this->output();
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $xlsx;
        exit;
    }
}

