<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.php");
    exit();
} 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';*/
// Ruta a TCPDF (cambia según donde tengas la librería)
require_once('TCPDF-main/tcpdf.php');
include 'config/database.php';
require '../includes/conexion.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['id_admin'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'aprobar_y_enviar':
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }

        // Obtener datos
        $query = "SELECT * FROM solicitudes_constancias WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            exit;
        }

        // Generar PDF
        $pdf_result = generarConstanciaPDF($solicitud);
        if (!$pdf_result['success']) {
            echo json_encode(['success' => false, 'message' => 'Error al generar PDF: ' . $pdf_result['message']]);
            exit;
        }

        // Enviar email
        //$email_result = enviarConstanciaPorEmail($solicitud, $pdf_result['file_path']);

        // Actualizar estado
        $query = "UPDATE solicitudes_constancias SET estado = 'aprobada', admin_id = :admin_id, fecha_aprobacion = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['admin_id' => $admin_id, ':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Constancia aprobada y enviada por email'/*, 'email_enviado' => $email_result*/]);
        break;

    case 'rechazar':
        $id = $_POST['id'] ?? '';
        $motivo = $_POST['motivo'] ?? 'No especificado';
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }

        $query = "SELECT email, nombre_completo FROM solicitudes_constancias WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($solicitud) {
            $asunto = "Rechazo de solicitud de constancia";
            $mensaje = "Hola {$solicitud['nombre_completo']},\n\nTu solicitud ha sido rechazada.\nMotivo: $motivo\n\nSaludos.";
            mail($solicitud['email'], $asunto, $mensaje, "From: constancias@tudominio.com");
        }

        $query = "UPDATE solicitudes_constancias SET estado = 'rechazada', admin_id = :admin_id, observaciones = :motivo, fecha_aprobacion = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['admin_id' => $admin_id, ':motivo' => $motivo, ':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Solicitud rechazada']);
        break;
}

// ========== FUNCIONES ==========

function generarConstanciaPDF($solicitud) {
    try {
        // Directorio temporal
        $temp_dir = __DIR__ . '/temp_constancias';
        if (!file_exists($temp_dir)) mkdir($temp_dir, 0777, true);

        // Rutas de imágenes (cámbialas según tu proyecto)
        $logo_unam = __DIR__ . '/assets/logounam.png';
        $logo_simposio = __DIR__ . '/assets/logosimposio.png';
        $firma = __DIR__ . '/assets/firma.png';

        // Crear PDF en horizontal (Landscape)
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // Borde decorativo
        $pdf->SetDrawColor(0, 51, 102);
        $pdf->Rect(10, 10, 277, 190, 'D');
        $pdf->SetDrawColor(191, 155, 48);
        $pdf->Rect(13, 13, 271, 184, 'D');

        // Logos
        if (file_exists($logo_unam)) $pdf->Image($logo_unam, 25, 20, 40, 0, 'PNG');
        if (file_exists($logo_simposio)) $pdf->Image($logo_simposio, 225, 20, 40, 0, 'PNG');

        // Títulos
        $pdf->SetY(45);
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 15, 'UNIVERSIDAD NACIONAL AUTÓNOMA DE MÉXICO', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(191, 155, 48);
        $pdf->Cell(0, 10, 'Facultad de Estudios Superiores Cuautitlán', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 10, 'OTORGA LA PRESENTE CONSTANCIA A:', 0, 1, 'C');
        $pdf->Ln(10);

        // Nombre
        $pdf->SetFont('helvetica', 'B', 26);
        $pdf->SetTextColor(191, 155, 48);
        $pdf->MultiCell(0, 20, strtoupper($solicitud['nombre_completo']), 0, 'C');
        $pdf->Ln(10);

        // Texto según rol
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(0, 10, "Por su valiosa participación como PONENTE en la conferencia:", 0, 'C');
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor(0, 51, 102);
            $pdf->MultiCell(0, 15, '"' . $solicitud['nombre_conferencia'] . '"', 0, 'C');

        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(0, 0, 0);
        $fecha = date('d/m/Y', strtotime($solicitud['fecha_participacion']));
        $pdf->MultiCell(0, 10, "celebrado el día " . $fecha . ", en las instalaciones de la", 0, 'C');
        $pdf->MultiCell(0, 10, "Facultad de Estudios Superiores Cuautitlán.", 0, 'C');
        $pdf->Ln(15);
        $pdf->MultiCell(0, 10, "Se extiende la presente constancia para los fines que al interesado convengan.", 0, 'C');
        $pdf->Ln(20);

        // Firma
        $pdf->SetY(-70);
        if (file_exists($firma)) {
            $pdf->Image($firma, 110, $pdf->GetY(), 70, 0, 'PNG');
            $pdf->SetY($pdf->GetY() + 30);
        }
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 5, '_________________________________________', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Dr. Carlos Enrique Arámburo de la Hoz', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Director de la FES Cuautitlán', 0, 1, 'C');

        $fecha_emision = date('d') . ' de ' . obtenerMes(date('m')) . ' de ' . date('Y');
        $pdf->SetY(-25);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 5, 'Cuautitlán Izcalli, a ' . $fecha_emision, 0, 1, 'C');

        // Guardar PDF
        $filename = 'constancia_' . preg_replace('/[^a-zA-Z0-9]/', '_', $solicitud['nombre_completo']) . '_' . time() . '.pdf';
        $filepath = $temp_dir . '/' . $filename;
        $pdf->Output($filepath, 'F');

        return ['success' => true, 'file_path' => $filepath, 'file_name' => $filename];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/*function enviarConstanciaPorEmail($solicitud, $pdf_path) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';       // Servidor SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'constancias@tudominio.com';  // Tu email
        $mail->Password   = '';        // Tu contraseña o app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('constancias@tudominio.com', 'Simposio Constancias');
        $mail->addAddress($solicitud['email'], $solicitud['nombre_completo']);
        $mail->addAttachment($pdf_path);
        
        $mail->isHTML(true);
        $mail->Subject = 'Tu constancia del Simposio 2025 está lista';
        $mail->Body    = "<h2>Constancia aprobada</h2><p>Hola {$solicitud['nombre_completo']},</p><p>Adjunto encontrarás tu constancia.</p><p>Gracias por participar.</p>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error enviando email: " . $mail->ErrorInfo);
        return false;
    }
}*/

function obtenerMes($mes) {
    $meses = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
    return $meses[$mes] ?? $mes;
}
?>