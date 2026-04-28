<?php
/**
 * MicroPdf — generador minimalista de PDF en PHP puro (HU-04.06).
 * No requiere ninguna librería externa ni Composer; produce PDF 1.4 binario.
 * Soporta: texto en Helvetica, líneas, rectángulos, tablas básicas.
 * Suficiente para la evidencia de préstamo del sistema UPB.
 */

class MicroPdf {
    private $objects = [];
    private $pages   = [];
    private $current = '';        // contenido de la página actual
    private $width   = 595.28;    // A4 en puntos
    private $height  = 841.89;
    private $margin  = 40;
    public  $y       = 0;         // cursor vertical (público para ajustes externos)
    private $title   = 'Documento UPB';

    public function __construct($title = 'Documento UPB') {
        $this->title = $title;
        $this->y = $this->margin;
    }

    /** Comienza una nueva página (cierra la actual si hay contenido). */
    public function nuevaPagina() {
        if (!empty($this->current)) {
            $this->pages[] = $this->current;
        }
        $this->current = '';
        $this->y = $this->margin;
    }

    /** Escribe una línea de texto. $size en puntos, $bold true|false. */
    public function texto($txt, $size = 11, $bold = false, $align = 'left', $color = '0 0 0') {
        $font = $bold ? '/F2' : '/F1';
        $txt  = $this->encode($txt);
        $w = $this->width - 2 * $this->margin;
        $x = $this->margin;
        if ($align === 'center') {
            $x = $this->width / 2 - ($size * 0.55 * mb_strlen($txt) / 2);
        } else if ($align === 'right') {
            $x = $this->width - $this->margin - ($size * 0.55 * mb_strlen($txt));
        }
        $py = $this->height - $this->y - $size;
        $this->current .= "BT $color rg $font $size Tf $x $py Td ($txt) Tj ET\n";
        $this->y += $size * 1.4;
    }

    /** Espacio vertical en blanco. */
    public function salto($puntos = 8) {
        $this->y += $puntos;
        if ($this->y > $this->height - $this->margin) $this->nuevaPagina();
    }

    /** Línea horizontal entre x1 e x2 a la posición actual. */
    public function linea($x1 = null, $x2 = null, $grosor = 0.5) {
        $x1 = $x1 ?? $this->margin;
        $x2 = $x2 ?? ($this->width - $this->margin);
        $py = $this->height - $this->y;
        $this->current .= sprintf("%.2f w %.2f %.2f m %.2f %.2f l S\n", $grosor, $x1, $py, $x2, $py);
        $this->y += 4;
    }

    /** Rectángulo coloreado de fondo. */
    public function rect($x, $y, $w, $h, $color = '0.95 0.95 0.95') {
        $py = $this->height - $y - $h;
        $this->current .= sprintf("%s rg %.2f %.2f %.2f %.2f re f\n", $color, $x, $py, $w, $h);
    }

    /** Tabla simple. $cols = ['Header'=>peso,...]; $rows = [[...],[...]] */
    public function tabla(array $headers, array $rows, array $widths = null) {
        $size = 9;
        $w = $this->width - 2 * $this->margin;
        $n = count($headers);
        $widths = $widths ?: array_fill(0, $n, $w / $n);
        $rowH = 16;

        // Header bg
        $this->rect($this->margin, $this->y, $w, $rowH, '0.96 0.92 0.97');
        $x = $this->margin + 4;
        foreach ($headers as $i => $h) {
            $py = $this->height - $this->y - $rowH + 5;
            $this->current .= "BT 0.42 0.12 0.49 rg /F2 $size Tf $x $py Td (" . $this->encode($h) . ") Tj ET\n";
            $x += $widths[$i];
        }
        $this->y += $rowH;

        foreach ($rows as $row) {
            if ($this->y > $this->height - $this->margin - $rowH) $this->nuevaPagina();
            $x = $this->margin + 4;
            foreach ($row as $i => $celda) {
                $py = $this->height - $this->y - $rowH + 5;
                $cel = mb_substr((string)$celda, 0, 50, 'UTF-8');
                $this->current .= "BT 0 0 0 rg /F1 $size Tf $x $py Td (" . $this->encode($cel) . ") Tj ET\n";
                $x += $widths[$i] ?? ($w / $n);
            }
            // separador
            $py = $this->height - $this->y - $rowH;
            $this->current .= sprintf("0.5 w 0.85 0.85 0.9 RG %.2f %.2f m %.2f %.2f l S\n",
                $this->margin, $py, $this->width - $this->margin, $py);
            $this->y += $rowH;
        }
    }

    /** Devuelve el PDF como string binario, listo para enviar. */
    public function output() {
        if (!empty($this->current)) $this->pages[] = $this->current;
        if (empty($this->pages)) $this->pages[] = '';

        $obj = [];

        // 1: Catalog
        $obj[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        // 2: Pages
        $kids = [];
        $startObj = 5;  // las páginas empiezan en obj 5
        $totalPages = count($this->pages);
        for ($i = 0; $i < $totalPages; $i++) {
            $kids[] = ($startObj + $i * 2) . " 0 R";
        }
        $obj[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $totalPages >>";

        // 3, 4: fonts
        $obj[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $obj[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        // 5,6,7,8...: cada página + su content stream
        $idx = 5;
        foreach ($this->pages as $cnt) {
            $stream = $cnt;
            $contentObj = $idx + 1;
            $obj[$idx] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->width} {$this->height}]"
                       . " /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >>"
                       . " /Contents $contentObj 0 R >>";
            $obj[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $idx += 2;
        }

        // ── Construir PDF binario ──
        $out  = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        ksort($obj);
        foreach ($obj as $i => $body) {
            $offsets[$i] = strlen($out);
            $out .= "$i 0 obj\n$body\nendobj\n";
        }
        $xrefPos = strlen($out);
        $maxObj  = max(array_keys($obj)) + 1;
        $out .= "xref\n0 $maxObj\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i < $maxObj; $i++) {
            if (isset($offsets[$i])) {
                $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                $out .= "0000000000 65535 f \n";
            }
        }
        $out .= "trailer\n<< /Size $maxObj /Root 1 0 R /Info << /Title (" . $this->encode($this->title) . ") /Producer (MicroPdf UPB) >> >>\n";
        $out .= "startxref\n$xrefPos\n%%EOF";
        return $out;
    }

    /** Codifica el texto a Latin-1 (ISO-8859-1) que es lo que entiende WinAnsi. */
    private function encode($s) {
        $s = (string)$s;
        // Convertir UTF-8 -> ISO-8859-1 (con descarte de caracteres no representables)
        $s = mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        // Escapar caracteres especiales del PDF
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        return $s;
    }
}
