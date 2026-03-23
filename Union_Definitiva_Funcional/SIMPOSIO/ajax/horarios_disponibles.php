<?php
require_once '../includes/conexion.php';
require_once '../includes/funciones.php';

header('Content-Type: application/json');

if (!isset($_GET['id_evento']) || !isset($_GET['tipo_trabajo'])) {
    echo json_encode(['error' => 'Faltan parámetros']);
    exit;
}

$id_evento = intval($_GET['id_evento']);
$tipo = $_GET['tipo_trabajo'];
$duracion = duracion_tipo_trabajo($tipo);

// Obtener fecha del evento
$fecha_evento = null;
$stmt = $conexion->prepare("SELECT fecha FROM evento WHERE id_evento = ?");
$stmt->bind_param("i", $id_evento);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $fecha_evento = $row['fecha'];
}

if (!$fecha_evento) {
    echo json_encode(['error' => 'Evento no encontrado']);
    exit;
}

// Si se pide una hora específica, devolver salones disponibles
if (isset($_GET['hora']) && !empty($_GET['hora'])) {
    $hora = $_GET['hora'];
    $hora_fin = date("H:i:s", strtotime($hora) + $duracion * 60);

    // Obtener IDs de salones ocupados en ese horario (cualquier evento de la misma fecha)
    $ocupados_ids = [];
    $stmt = $conexion->prepare("
        SELECT DISTINCT id_salon FROM actividad_evento 
        WHERE fecha = ? 
          AND id_salon IS NOT NULL
          AND (
              (hora_inicio < ? AND hora_fin > ?)
              OR (hora_inicio = ?)
          )
    ");
    $stmt->bind_param("ssss", $fecha_evento, $hora_fin, $hora, $hora);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ocupados_ids[] = $row['id_salon'];
    }

    // Obtener todos los salones
    $todos_salones = [];
    $res_salones = $conexion->query("SELECT id_salon, nombre FROM salones ORDER BY nombre");
    while ($row = $res_salones->fetch_assoc()) {
        $todos_salones[] = $row;
    }

    // Filtrar los no ocupados
    $disponibles = array_filter($todos_salones, function($s) use ($ocupados_ids) {
        return !in_array($s['id_salon'], $ocupados_ids);
    });

    echo json_encode(array_values($disponibles));
    exit;
}

// Si no, devolver horarios disponibles
$horarios = obtener_horarios_disponibles($conexion, $id_evento, $duracion);
echo json_encode($horarios);
?>