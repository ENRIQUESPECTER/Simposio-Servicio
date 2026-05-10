<?php
session_start();
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/notificaciones.php';

if (!esta_logeado()) {
    header('Location: ../login.php');
    exit;
}

marcar_todas_notificaciones_leidas($conexion, $_SESSION['id_usuario']);
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
exit;
?>