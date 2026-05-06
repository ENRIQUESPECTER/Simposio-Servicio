<?php
session_start();
require_once '../includes/conexion.php';
require_once '../includes/auth.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}

require_once '../libs/fpdf186/fpdf.php';
require_once '../libs/FPDI-2.6.6/src/autoload.php';
use setasign\Fpdi\Fpdi;

set_time_limit(300);
ini_set('memory_limit', '512M');

// Ruta base absoluta del proyecto (cambia 'SIMPOSIO' por el nombre real)
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
    die("No hay trabajos aprobados con archivos PDF para compilar.");
}

$pdf = new Fpdi();
$errores = [];

foreach ($result as $row) {
    $pdf_path = $base_path . $row['archivo_pdf'];
    if (!file_exists($pdf_path)) {
        $errores[] = "PDF no encontrado: " . $row['archivo_pdf'];
        continue;
    }
    if (!is_readable($pdf_path)) {
        $errores[] = "PDF no legible: " . $row['archivo_pdf'];
        continue;
    }

    // Portada
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Trabajo: ' . mb_convert_encoding($row['titulo'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 8, 'Autor(es): ' . mb_convert_encoding($row['autor_nombre'] . ' ' . $row['autor_apellidos'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 8, 'Evento: ' . mb_convert_encoding($row['evento_titulo'] . ' (' . $row['evento_fecha'] . ')', 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 8, 'Tipo: ' . ucfirst($row['tipo_trabajo']) . ' | Categoría: ' . $row['categoria'], 0, 1);
    if (!empty($row['resumen'])) {
        $pdf->MultiCell(0, 6, 'Resumen: ' . mb_convert_encoding($row['resumen'], 'ISO-8859-1', 'UTF-8'));
    }
    $pdf->Ln(10);

    // Importar páginas
    try {
        $pageCount = $pdf->setSourceFile($pdf_path);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $template = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }
    } catch (Exception $e) {
        $errores[] = "Error al importar {$row['titulo']}: " . $e->getMessage();
        continue;
    }
}

// Si ocurrieron errores, registrarlos en el log (sin mostrar en pantalla)
if (!empty($errores)) {
    error_log(implode("\n", $errores));
}

// Generar PDF
$pdf->Output('Revista_Simposio.pdf', 'D');
exit;
?>