<?php
session_start();
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../libs/pdfparser-master/src/Smalot/PdfParser/Parser.php'; // Composer autoload
require_once '../libs/pdfparser-master/alt_autoload.php';

use Smalot\PdfParser\Parser;
use setasign\Fpdi\Fpdi; // solo para portada, opcional 

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/SIMPOSIO/';

$sql = "SELECT a.id_articulo, a.titulo, a.tipo_trabajo, a.categoria, a.resumen,
               u.nombre as autor_nombre, u.apellidos as autor_apellidos,
               e.titulo as evento_titulo, e.fecha as evento_fecha,
               ae.archivo_pdf
        FROM articulo a
        JOIN actividad_evento ae ON a.id_articulo = ae.id_articulo
        JOIN usuario u ON a.id_usuario = u.id_usuario
        JOIN evento e ON a.id_evento = e.id_evento
        WHERE a.estado = 'aprobado' AND ae.archivo_pdf IS NOT NULL AND ae.archivo_pdf != ''
        ORDER BY a.fecha_registro ASC";
$result = $conexion->query($sql);
if (!$result || $result->num_rows == 0) {
    die("No hay trabajos aprobados con PDFs.");
}

require_once '../libs/fpdf186/fpdf.php';
require_once '../libs/FPDI-2.6.6/src/autoload.php';
$pdf = new FPDF();
$parser = new Parser();

foreach ($result as $row) {
    $pdf_path = $base_path . $row['archivo_pdf'];
    if (!file_exists($pdf_path)) continue;

    // ---- Portada mejorada ----
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 15, mb_convert_encoding('TRABAJO ACADÉMICO', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C', true);
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, mb_convert_encoding($row['titulo'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Autor(es): ' . mb_convert_encoding($row['autor_nombre'] . ' ' . $row['autor_apellidos'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 7, 'Evento: ' . mb_convert_encoding($row['evento_titulo'] . ' (' . $row['evento_fecha'] . ')', 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 7, 'Tipo: ' . ucfirst($row['tipo_trabajo']) . utf8_decode(' | Categoría: ') . utf8_decode($row['categoria']), 0, 1);
    if (!empty($row['resumen'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->MultiCell(0, 5, 'Resumen: ' . mb_convert_encoding($row['resumen'], 'ISO-8859-1', 'UTF-8'));
    }
    $pdf->Ln(15);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(10);

    // ---- Extraer texto del PDF ----
    try {
        $parsed = $parser->parseFile($pdf_path);
        $texto = $parsed->getText();
        // Limpieza de espacios múltiples
        $texto = preg_replace('/\s+/', ' ', $texto);
        $texto = trim($texto);
        $texto = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');

        // Insertar texto en una nueva página (o en la misma página si cabe)
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, $texto);
    } catch (Exception $e) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 6, 'No se pudo extraer el texto de este PDF. Puede estar protegido o ser una imagen.', 0, 1);
    }
}

$pdf->Output('Revista_Simposio.pdf', 'D');
exit;
?>