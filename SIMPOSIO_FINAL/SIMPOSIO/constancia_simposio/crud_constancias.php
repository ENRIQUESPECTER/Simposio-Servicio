<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include 'config/database.php';

$db = (new Database())->getConnection();
$action = $_GET['action'] ?? '';

if ($action === 'read_pendientes') {
    $query = "SELECT * FROM solicitudes_constancias WHERE estado = 'pendiente' ORDER BY fecha_solicitud DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'solicitudes' => $solicitudes]);
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>