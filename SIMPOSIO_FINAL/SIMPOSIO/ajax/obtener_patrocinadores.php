<?php
session_start();
require_once '../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_articulo = intval($_GET['id'] ?? 0);
if (!$id_articulo) {
    echo json_encode(['success' => false, 'message' => 'ID de proyecto inválido']);
    exit;
}

$sql = "SELECT p.*, e.nombre_empresa, u.nombre, u.apellidos, u.correo
        FROM patrocinios p
        JOIN empresa e ON p.id_empresa = e.id_empresa
        JOIN usuario u ON e.id_usuario = u.id_usuario
        WHERE p.id_articulo = ? AND p.estado = 'aceptado'
        ORDER BY p.fecha_respuesta DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_articulo);
$stmt->execute();
$result = $stmt->get_result();

$patrocinadores = [];
while ($row = $result->fetch_assoc()) {
    $patrocinadores[] = [
        'nombre_empresa' => $row['nombre_empresa'],
        'nombre' => $row['nombre'],
        'apellidos' => $row['apellidos'],
        'correo' => $row['correo'],
        'fecha_respuesta' => $row['fecha_respuesta'],
        'comentarios_empresa' => $row['comentarios_empresa']
    ];
}

echo json_encode([
    'success' => true,
    'patrocinadores' => $patrocinadores,
    'total' => count($patrocinadores)
]);
?>