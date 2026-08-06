<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include 'config/database.php';
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/funciones.php';
require_once '../includes/notificaciones.php';


$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre_completo'] ?? '';
    $email = $_POST['email'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $conferencia = $_POST['evento'] ?? '';
    $staff_rol = $_POST['rol_staff'] ?? '';
    $fecha = $_POST['fecha_participacion'] ?? '';

    if (empty($nombre) || empty($email) || empty($fecha)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
        exit;
    }

    $query = "INSERT INTO solicitudes_constancias (nombre_completo, email, rol, nombre_conferencia, rol_staff, fecha_participacion) 
              VALUES (:nombre, :email, :rol, :conferencia, :staff, :fecha)";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':rol' => $rol,
        ':conferencia' => $conferencia,
        ':staff' => $staff_rol,
        ':fecha' => $fecha
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Solicitud enviada correctamente. Recibirás un correo cuando sea aprobada.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al enviar la solicitud']);
    }
}
?>