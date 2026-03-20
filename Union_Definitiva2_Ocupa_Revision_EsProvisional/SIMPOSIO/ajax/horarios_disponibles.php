<?php
require_once '../includes/conexion.php';
require_once '../includes/funciones.php';

header('Content-Type: application/json');

if (!isset($_GET['id_evento']) || !isset($_GET['tipo_trabajo'])) {
    echo json_encode(['error' => 'Faltan parámetros']);
    exit;
}

$id_evento = intval($_GET['id_evento']);
$tipo_trabajo = $_GET['tipo_trabajo'];

$duracion = duracion_tipo_trabajo($tipo_trabajo);
$horarios = obtener_horarios_disponibles($conexion, $id_evento, $duracion);

echo json_encode($horarios);
?>