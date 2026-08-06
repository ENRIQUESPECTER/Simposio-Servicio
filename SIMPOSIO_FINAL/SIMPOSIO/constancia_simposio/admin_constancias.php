<?php
session_start();
require "../includes/conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: ../admin/login_admin.php");
    exit();
} 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Constancias</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="../Css/admin.css">
    <style>
        /* admin.css */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-top: 7.5rem;
        }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #003366; color: white; }
        button { margin: 0 5px; padding: 5px 10px; cursor: pointer; }
        .btn-aprobar { background: #28a745; color: white; border: none; }
        .btn-rechazar { background: #dc3545; color: white; border: none; }
        .error-msg { color: red; margin-top: 10px; }
        .success-msg { color: green; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" style="background-color: #293e6b;">
        <div class="container-fluid">
            <a class="navbar-brand" href="../admin/index.php">
                <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../admin/index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="../admin/eventos/lista_eventos.php"><i class="fas fa-scroll me-1"></i>Lista Eventos</a></li>
                    <li class="nav-item"><a class="nav-link" href="../admin/actividades/lista_actividades.php"><i class="fas fa-chalkboard me-1"></i>Agenda Actividades</a></li>
                    <li class="nav-item"><a class="nav-link" href="../admin/trabajos/pendientes.php"><i class="fas fa-calendar me-1"></i>Evaluación de Trabajos</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['usuario'];  ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="../admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                            </ul>
                        </li>
                </ul>
            </div>
        </div>
    </nav>
    <h2>Gestión de Solicitudes de Constancias</h2>
    <div id="mensaje"></div>
    <div id="lista"></div>

    <script>
        async function cargarSolicitudes() {
            try {
                const response = await fetch('crud_constancias.php?action=read_pendientes');
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                const div = document.getElementById('lista');
                if (!data.success || !data.solicitudes || data.solicitudes.length === 0) {
                    div.innerHTML = '<p>No hay solicitudes pendientes.</p>';
                    return;
                }
                let html = `<table>
                    <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Fecha Participación</th><th>Acciones</th></tr></thead>
                    <tbody>`;
                data.solicitudes.forEach(s => {
                    html += `<tr>
                        <td>${escapeHtml(s.nombre_completo)}</td>
                        <td>${escapeHtml(s.email)}</td>
                        <td>${escapeHtml(s.rol)}</td>
                        <td>${escapeHtml(s.fecha_participacion)}</td>
                        <td>
                            <button class="btn-aprobar" onclick="aprobar(${s.id})">✅ Aprobar</button>
                            <button class="btn-rechazar" onclick="rechazar(${s.id})">❌ Rechazar</button>
                        </td>
                    </tr>`;
                });
                html += '</tbody></table>';
                div.innerHTML = html;
            } catch (error) {
                mostrarMensaje('Error al cargar solicitudes: ' + error.message, 'error');
            }
        }

        async function aprobar(id) {
            if (!confirm('¿Aprobar y enviar constancia?')) return;
            mostrarMensaje('Procesando...', 'info');
            try {
                const fd = new FormData();
                fd.append('action', 'aprobar_y_enviar');
                fd.append('id', id);
                const response = await fetch('procesar_constancia_completo.php', { method: 'POST', body: fd });
                const text = await response.text(); // Primero obtener texto para depurar
                console.log('Respuesta cruda:', text);
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    throw new Error('Respuesta no es JSON: ' + text.substring(0,200));
                }
                mostrarMensaje(data.message, data.success ? 'success' : 'error');
                if (data.success) cargarSolicitudes();
            } catch (error) {
                mostrarMensaje('Error en la petición: ' + error.message, 'error');
            }
        }

        async function rechazar(id) {
            const motivo = prompt('Motivo del rechazo:');
            if (motivo === null) return;
            mostrarMensaje('Procesando...', 'info');
            try {
                const fd = new FormData();
                fd.append('action', 'rechazar');
                fd.append('id', id);
                fd.append('motivo', motivo);
                const response = await fetch('procesar_constancia_completo.php', { method: 'POST', body: fd });
                const text = await response.text();
                console.log('Respuesta rechazo:', text);
                let data = JSON.parse(text);
                mostrarMensaje(data.message, data.success ? 'success' : 'error');
                if (data.success) cargarSolicitudes();
            } catch (error) {
                mostrarMensaje('Error: ' + error.message, 'error');
            }
        }

        function mostrarMensaje(msg, tipo) {
            const div = document.getElementById('mensaje');
            div.innerHTML = `<div class="${tipo === 'error' ? 'error-msg' : 'success-msg'}">${msg}</div>`;
            setTimeout(() => div.innerHTML = '', 5000);
        }

        function escapeHtml(str) {
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        cargarSolicitudes();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>