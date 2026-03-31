<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';

// Verificar autenticación
if (!esta_logeado()) {
    header('Location: login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: mis_proyectos.php');
    exit;
}

// Verificar que el usuario es autor del proyecto
$es_autor = false;
$stmt = $conexion->prepare("SELECT id_usuario, estado FROM articulo WHERE id_articulo = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$articulo = $stmt->get_result()->fetch_assoc();

if (!$articulo) {
    header('Location: mis_proyectos.php');
    exit;
}

// Comprobar si es autor (empresa)
if ($articulo['id_usuario'] == $_SESSION['id_usuario']) {
    $es_autor = true;
} else {
    // Verificar si es alumno o docente autor
    $tipo = $_SESSION['tipo_usuario'] ?? '';
    if ($tipo == 'alumno') {
        $stmt = $conexion->prepare("SELECT 1 FROM articulo_alumno WHERE id_articulo = ? AND id_alumno = (SELECT id_alumno FROM alumno WHERE id_usuario = ?) AND rol = 'autor'");
        $stmt->bind_param("ii", $id, $_SESSION['id_usuario']);
        $stmt->execute();
        $es_autor = $stmt->get_result()->num_rows > 0;
    } elseif ($tipo == 'docente') {
        $stmt = $conexion->prepare("SELECT 1 FROM articulo_docente WHERE id_articulo = ? AND id_docente = (SELECT id_docente FROM docente WHERE id_usuario = ?)");
        $stmt->bind_param("ii", $id, $_SESSION['id_usuario']);
        $stmt->execute();
        $es_autor = $stmt->get_result()->num_rows > 0;
    }
}

if (!$es_autor) {
    $_SESSION['mensaje'] = "No tienes permiso para reenviar este trabajo.";
    header('Location: mis_proyectos.php');
    exit;
}

if ($articulo['estado'] != 'rechazado') {
    $_SESSION['mensaje'] = "Este trabajo no está rechazado, no puede reenviarse.";
    header('Location: ver_proyecto.php?id=' . $id);
    exit;
}

// Cambiar estado a pendiente y mostrar la actividad
$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare("UPDATE articulo SET estado = 'pendiente' WHERE id_articulo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt2 = $conexion->prepare("UPDATE actividad_evento SET visible = 1 WHERE id_articulo = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();

    $conexion->commit();
    $_SESSION['mensaje'] = "Trabajo reenviado para aprobación.";
    $_SESSION['tipo_mensaje'] = "success";
} catch (Exception $e) {
    $conexion->rollback();
    $_SESSION['mensaje'] = "Error al reenviar: " . $e->getMessage();
    $_SESSION['tipo_mensaje'] = "danger";
}

header('Location: ver_proyecto.php?id=' . $id);
exit;