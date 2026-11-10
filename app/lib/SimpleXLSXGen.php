<?php
declare(strict_types=1);

/**
 * CyberPOS 2.0 - SimpleXLSXGen
 * Generador nativo OpenXML (.xlsx) en PHP puro (sin Composer).
 * Empaqueta hojas de cálculo válidas para Microsoft Excel, LibreOffice y Google Sheets.
 */
class SimpleXLSXGen
{
    private string $empresa = 'LANEROS GAMER C.A. - SISTEMA CYBERPOS 2.0';
    private string $title;
    private string $fechaEmision = '';
    private string $usuarioResponsable = '';
    private string $filtrosAplicados = '';
    private float $tasaBcv = 36.85;

    private array $headers = [];
    private array $rows = [];
    private array $types = [];
    private array $kpis = [];
    private ?array $totals = null;
    private string $metaInfo = '';

    public function __construct(string $title = 'Reporte')
    {
        $this->title = $title;
        $this->fechaEmision = date('d/m/Y H:i:s');
    }

    public function setInstitutionalHeader(
        string $titulo,
        string $fechaEmision,
        string $usuarioResponsable,
        string $filtros,
        float $tasaBcv
    ): self {
        $this->title = $titulo;
        $this->fechaEmision = $fechaEmision;
        $this->usuarioResponsable = $usuarioResponsable;
        $this->filtrosAplicados = $filtros;
        $this->tasaBcv = $tasaBcv;
        return $this;
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

        $colCount = max(count($this->headers), 1);
        $xml .= '  <cols>' . "\n";
        for ($c = 1; $c <= $colCount; $c++) {
            $xml .= sprintf('    <col min="%d" max="%d" width="23" customWidth="1"/>' . "\n", $c, $c);
        }
        $xml .= '  </cols>' . "\n";

        $xml .= '  <sheetData>' . "\n";
        $r = 1;

        // Fila 1: LANEROS GAMER C.A. - SISTEMA CYBERPOS 2.0
        $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
        $xml .= sprintf('      <c r="A%d" s="1" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $r, $this->escapeXml($this->empresa));
        $xml .= '    </row>' . "\n";
        $r++;

        // Fila 2: Título del Reporte Gerencial
        $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
        $xml .= sprintf('      <c r="A%d" s="2" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $r, $this->escapeXml($this->title));
        $xml .= '    </row>' . "\n";
        $r++;

        // Fila 3: Fecha/Hora de emisión y Usuario Responsable
        $emisionResp = sprintf(
            'Fecha y Hora de Emisión: %s | Usuario Responsable: %s',
            $this->fechaEmision !== '' ? $this->fechaEmision : date('d/m/Y H:i:s'),
            $this->usuarioResponsable !== '' ? $this->usuarioResponsable : 'Administrador de Turno (administrador)'
        );
        $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
        $xml .= sprintf('      <c r="A%d" s="0" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $r, $this->escapeXml($emisionResp));
        $xml .= '    </row>' . "\n";
        $r++;

        // Fila 4: Filtros aplicados (Desde, Hasta, Tasa BCV Vigente)
        $filtrosStr = sprintf(
            'Filtros Aplicados: %s | Tasa BCV Vigente: %s Bs/$',
            $this->filtrosAplicados !== '' ? $this->filtrosAplicados : 'Período Completo',
            number_format($this->tasaBcv, 2, ',', '.')
        );
        $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
        $xml .= sprintf('      <c r="A%d" s="0" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $r, $this->escapeXml($filtrosStr));
        $xml .= '    </row>' . "\n";
        $r++;

        // Fila de KPIs si existen
        if (!empty($this->kpis)) {
            $r++; // Espacio
            $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, $colCount);
            foreach ($this->kpis as $ki => $kpi) {
                if ($ki >= $colCount) break;
                $col = $this->colLetter($ki);
                $lbl = ($kpi['label'] ?? '') . ': ' . ($kpi['value'] ?? '');
                $xml .= sprintf('      <c r="%s%d" s="4" t="inlineStr"><is><t>%s</t></is></c>' . "\n", $col, $r, $this->escapeXml($lbl));
            }
            $xml .= '    </row>' . "\n";
            $r++;
        }

        $r++; // Espacio antes de la tabla

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

        // Fila Final de Totales / KPIs
        if (!empty($this->totals)) {
            $xml .= sprintf('    <row r="%d" spans="1:%d">' . "\n", $r, count($this->totals));
            foreach ($this->totals as $ci => $val) {
                $col = $this->colLetter($ci);
                $type = $this->types[$ci] ?? 'string';

                if (is_numeric($val) && ($type === 'number' || $type === 'currency' || $type === 'percent' || $type === 'int')) {
                    $styleId = 9;
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
'    <font><name val="Segoe UI"/><sz val="10"/><color rgb="FF1F2937"/></font>' . "\n" .
'    <font><b/><name val="Segoe UI"/><sz val="13"/><color rgb="FF35C029"/></font>' . "\n" .
'    <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FF111827"/></font>' . "\n" .
'    <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FFFFFFFF"/></font>' . "\n" .
'  </fonts>' . "\n" .
'  <fills count="4">' . "\n" .
'    <fill><patternFill patternType="none"/></fill>' . "\n" .
'    <fill><patternFill patternType="gray125"/></fill>' . "\n" .
'    <fill><patternFill patternType="solid"><fgColor rgb="FF1A3822"/></patternFill></fill>' . "\n" .
'    <fill><patternFill patternType="solid"><fgColor rgb="FFE5F5E0"/></patternFill></fill>' . "\n" .
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
'    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="0" fontId="1" fillId="0" borderId="0"/>' . "\n" .
'    <xf numFmtId="0" fontId="2" fillId="0" borderId="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyFill="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" applyFill="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="166" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="167" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" .
'    <xf numFmtId="164" fontId="2" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/>' . "\n" .
'  </cellXfs>' . "\n" .
'</styleSheet>';
    }

    private function packZip(array $files): string
    {
        $dataEntries = '';
        $dirEntries  = '';
        $offset      = 0;

        foreach ($files as $name => $content) {
            $uncompressedSize = strlen($content);
            $crc = crc32($content);

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
            $localHeader = pack(
                'VvvvVVVvv',
                0x04034b50,
                20,
                0,
                $compressionMethod,
                0,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0
            ) . $name;

            $dataEntries .= $localHeader . $compressed;

            $dirHeader = pack(
                'VvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                $compressionMethod,
                0,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $name;

            $dirEntries .= $dirHeader;
            $offset = strlen($dataEntries);
        }

        $dirSize = strlen($dirEntries);
        $totalFiles = count($files);

        $endRecord = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $totalFiles,
            $totalFiles,
            $dirSize,
            $offset,
            0
        );

        return $dataEntries . $dirEntries . $endRecord;
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

        $packageRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n" .
'  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n" .
'</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
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
            '_rels/.rels'                => $packageRels,
            'xl/_rels/workbook.xml.rels' => $workbookRels,
            'xl/workbook.xml'            => $workbook,
            'xl/styles.xml'              => $this->buildStylesXml(),
            'xl/worksheets/sheet1.xml'   => $this->buildSheetXml(),
        ];

        if (class_exists('ZipArchive')) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
            $zip = new ZipArchive();
            if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($files as $name => $content) {
                    $zip->addFromString($name, $content);
                }
                $zip->close();
                $data = file_get_contents($tmpFile);
                @unlink($tmpFile);
                return $data ?: '';
            }
        }

        return $this->packZip($files);
    }

    public function download(string $filename = 'reporte.xlsx'): void
    {
        $bin = $this->output();
        if (headers_sent()) {
            echo $bin;
            exit;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $bin;
        exit;
    }
}
