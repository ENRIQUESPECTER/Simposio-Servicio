<?php
session_start();

function esta_logeado() {
    return isset($_SESSION['id_usuario']);
}
function es_alumno() {
    return (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'alumno');
}
function es_docente() {
    return (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'docente');
}
function es_empresa() {
    return (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'empresa');
}
?>