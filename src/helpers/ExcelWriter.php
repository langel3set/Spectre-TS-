<?php
declare(strict_types=1);

/**
 * src/helpers/ExcelWriter.php
 *
 * Generador ligero de archivos Excel (.xlsx) sin depender de librerías
 * externas (PhpSpreadsheet, etc). Construye un .xlsx válido a mano usando
 * ZipArchive. Si la extensión `zip` no está disponible en el servidor,
 * cae automáticamente a un .csv (que Excel también abre sin problema).
 *
 * Uso:
 *   ExcelWriter::descargar('reporte_gastos', ['Fecha', 'Concepto', 'Monto'], [
 *       ['2026-07-01', 'Alquiler', 1200.00],
 *       ['2026-07-02', 'Luz', 85.50],
 *   ]);
 */
class ExcelWriter
{
    /**
     * Genera el archivo y lo envía directamente al navegador (headers + exit).
     *
     * @param string $nombreArchivo Nombre sin extensión.
     * @param array  $encabezados   Lista de títulos de columna.
     * @param array  $filas         Lista de filas (cada fila es un array de valores en el mismo orden que $encabezados).
     */
    public static function descargar(string $nombreArchivo, array $encabezados, array $filas): void
    {
        if (class_exists('ZipArchive')) {
            $bytes = self::generarXlsx($encabezados, $filas);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xlsx"');
            header('Content-Length: ' . strlen($bytes));
            echo $bytes;
            exit;
        }

        // ── Fallback: CSV con BOM UTF-8 (Excel lo abre correctamente) ──
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $encabezados, ';');
        foreach ($filas as $fila) {
            fputcsv($out, $fila, ';');
        }
        fclose($out);
        exit;
    }

    /**
     * Construye el binario del .xlsx (una sola hoja, celdas tipo inlineStr/number).
     */
    private static function generarXlsx(array $encabezados, array $filas): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::relsRaiz());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relsWorkbook());
        $zip->addFromString('xl/workbook.xml', self::workbookXml());
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($encabezados, $filas));

        $zip->close();
        $bytes = (string)file_get_contents($tmp);
        unlink($tmp);
        return $bytes;
    }

    private static function contentTypes(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private static function relsRaiz(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private static function relsWorkbook(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private static function workbookXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Datos" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private static function stylesXml(): string
    {
        // Estilo 0 = normal, Estilo 1 = encabezado en negrita.
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" xfId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>
XML;
    }

    private static function colLetra(int $indice): string
    {
        $letra = '';
        $indice++;
        while ($indice > 0) {
            $mod = ($indice - 1) % 26;
            $letra = chr(65 + $mod) . $letra;
            $indice = intdiv($indice - $mod, 26);
        }
        return $letra;
    }

    private static function escaparXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function sheetXml(array $encabezados, array $filas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        // Fila de encabezados (estilo 1 = negrita)
        $xml .= '<row r="1">';
        foreach ($encabezados as $i => $enc) {
            $ref = self::colLetra($i) . '1';
            $xml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">' . self::escaparXml((string)$enc) . '</t></is></c>';
        }
        $xml .= '</row>';

        // Filas de datos
        foreach ($filas as $rIdx => $fila) {
            $numFila = $rIdx + 2;
            $xml .= '<row r="' . $numFila . '">';
            foreach (array_values($fila) as $cIdx => $valor) {
                $ref = self::colLetra($cIdx) . $numFila;
                if (is_int($valor) || is_float($valor)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $valor . '</v></c>';
                } elseif (is_numeric($valor) && $valor !== '' && $valor[0] !== '0') {
                    $xml .= '<c r="' . $ref . '"><v>' . $valor . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::escaparXml((string)$valor) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }
}
