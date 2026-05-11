<?php
session_start();
require_once '../includes/conexion.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado - Sesión no iniciada']);
    exit;
}

$id_articulo = intval($_GET['id'] ?? 0);
if (!$id_articulo) {
    echo json_encode(['success' => false, 'message' => 'ID de proyecto inválido']);
    exit;
}

// Verificar conexión a BD
if (!$conexion) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

$stmt = $conexion->prepare("
    SELECT 
        p.id_patrocinio,
        p.comentarios_empresa,
        p.comentarios_autor,
        p.fecha_respuesta,
        e.nombre_empresa,
        u.nombre,
        u.apellidos,
        u.correo
    FROM patrocinios p
    JOIN empresa e ON p.id_empresa = e.id_empresa
    JOIN usuario u ON e.id_usuario = u.id_usuario
    WHERE p.id_articulo = ? AND p.estado = 'aceptado'
    ORDER BY p.fecha_respuesta DESC
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en preparación de consulta: ' . $conexion->error]);
    exit;
}

$stmt->bind_param("i", $id_articulo);
$stmt->execute();
$result = $stmt->get_result();

$patrocinadores = [];
while ($row = $result->fetch_assoc()) {
    $patrocinadores[] = $row;
}

echo json_encode([
    'success' => true,
    'patrocinadores' => $patrocinadores,
    'total' => count($patrocinadores)
]);
?>