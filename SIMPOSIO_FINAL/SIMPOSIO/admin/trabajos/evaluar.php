<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/config.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

// Obtener datos del artículo y su PDF
$stmt = $conexion->prepare("
    SELECT a.*, ae.archivo_pdf, ae.id_actividad,
           u.nombre as autor_nombre, u.apellidos as autor_apellidos
    FROM articulo a
    JOIN actividad_evento ae ON a.id_articulo = ae.id_articulo
    JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE a.id_articulo = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$trabajo = $stmt->get_result()->fetch_assoc();

if (!$trabajo || empty($trabajo['archivo_pdf'])) {
    die("Trabajo no encontrado o sin archivo PDF.");
}

// Definir 10 criterios (5 originales + 5 adicionales)
$criterios = [
    ['id' => 'paginas', 'texto' => 'El documento tiene exactamente 8 páginas (cuartillas).'],
    ['id' => 'fuente', 'texto' => 'La fuente utilizada es Arial (o similar, legible).'],
    ['id' => 'explicacion', 'texto' => 'Se explica claramente el proyecto, sus objetivos y alcance.'],
    ['id' => 'estructura', 'texto' => 'El documento sigue una estructura académica: introducción, desarrollo, conclusiones.'],
    ['id' => 'ortografia', 'texto' => 'La redacción es clara y sin errores ortográficos graves.'],
    ['id' => 'metodologia', 'texto' => 'La metodología empleada es adecuada y está bien descrita.'],
    ['id' => 'resultados', 'texto' => 'Los resultados presentados son claros y relevantes.'],
    ['id' => 'referencias', 'texto' => 'Las referencias bibliográficas son actualizadas y pertinentes.'],
    ['id' => 'originalidad', 'texto' => 'El trabajo muestra originalidad y aporta valor al área.'],
    ['id' => 'impacto', 'texto' => 'El proyecto tiene potencial de impacto social o tecnológico.']
];

$resultado_ia = null;
$error_ia = null;

// Procesar evaluación con IA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['evaluar_ia'])) {
    $api_key = GEMINI_API_KEY;
    $ruta_pdf = '../../' . $trabajo['archivo_pdf'];
    $resultado_ia = evaluar_extenso_con_gemini($ruta_pdf, $api_key);
    if (isset($resultado_ia['error'])) {
        $error_ia = $resultado_ia['error'];
        $resultado_ia = null;
    }
}

// Procesar rechazo (con detalles)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rechazar'])) {
    // Guardar detalles de cada criterio
    $detalles = $_POST['detalles'] ?? [];
    if (!empty($detalles)) {
        // Eliminar detalles anteriores si existían (opcional, pero mejor limpiar)
        $stmt_d = $conexion->prepare("DELETE FROM revision_detalles WHERE id_articulo = ?");
        $stmt_d->bind_param("i", $id);
        $stmt_d->execute();
        $stmt_det = $conexion->prepare("INSERT INTO revision_detalles (id_articulo, criterio, detalle) VALUES (?, ?, ?)");
        foreach ($detalles as $criterio_id => $texto) {
            if (!empty(trim($texto))) {
                $criterio_nombre = '';
                foreach ($criterios as $c) {
                    if ($c['id'] == $criterio_id) {
                        $criterio_nombre = $c['texto'];
                        break;
                    }
                }
                $stmt_det->bind_param("iss", $id, $criterio_nombre, $texto);
                $stmt_det->execute();
            }
        }
    }
    
    // Ahora ejecutar el rechazo (cambiar estado y ocultar actividad)
    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare("UPDATE articulo SET estado = 'rechazado' WHERE id_articulo = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt2 = $conexion->prepare("UPDATE actividad_evento SET visible = 0 WHERE id_articulo = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $conexion->commit();
        
        // Opcional: enviar notificación al autor (se puede hacer con un mensaje en la sesión)
        $_SESSION['mensaje_notificacion'] = "El trabajo ha sido rechazado. Se han enviado los detalles al autor.";
        header("Location: ../index.php?mensaje=rechazado");
        exit;
    } catch (Exception $e) {
        $conexion->rollback();
        $error_ia = "Error al rechazar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluar extenso - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="../../Css/admin.css">
    <style>
        .pdf-viewer {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .table-criterios th, .table-criterios td {
            vertical-align: middle;
        }
        textarea {
            width: 100%;
            min-width: 200px;
        }
        .alerta-detalle {
            font-size: 0.9rem;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" style="background-color: #293e6b;">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="../eventos/lista_eventos.php"><i class="fas fa-scroll me-1"></i>Lista Eventos</a></li>
                <li class="nav-item"><a class="nav-link" href="../actividades/lista_actividades.php"><i class="fas fa-chalkboard me-1"></i>Agenda Actividades</a></li>
                <li class="nav-item"><a class="nav-link" href="../trabajos/pendientes.php"><i class="fas fa-calendar me-1"></i>Evaluación de Trabajos</a></li>
                <li class="nav-item"><a class="nav-link" href="../trabajos/evaluacion.php"><i class="fas fa-calendar me-1"></i>Evaluación Extensos</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['usuario']  ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </li>
            </ul>
        </div>
    </div>
</nav>
    <div class="container mt-5">
        <h2>Evaluar extenso: <?php echo htmlspecialchars($trabajo['titulo']); ?></h2>
        <p><strong>Autor:</strong> <?php echo htmlspecialchars($trabajo['autor_nombre'] . ' ' . $trabajo['autor_apellidos']); ?></p>
        <p><strong>Estado actual:</strong> 
            <span class="badge bg-<?php echo $trabajo['estado'] == 'aprobado' ? 'success' : ($trabajo['estado'] == 'rechazado' ? 'danger' : 'warning'); ?>">
                <?php echo ucfirst($trabajo['estado']); ?>
            </span>
        </p>

        <!-- Visor del PDF -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Vista previa del documento</div>
            <div class="card-body">
                <embed class="pdf-viewer" src="../../<?php echo $trabajo['archivo_pdf']; ?>" type="application/pdf">
                <div class="text-center mt-2">
                    <a href="../../<?php echo $trabajo['archivo_pdf']; ?>" target="_blank" class="btn btn-sm btn-info">Abrir en nueva pestaña</a>
                </div>
            </div>
        </div>

        <!-- Evaluación con IA -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Evaluación con IA</div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <button type="submit" name="evaluar_ia" class="btn btn-primary">
                        <i class="fas fa-brain"></i> Evaluar con IA
                    </button>
                </form>

                <?php if ($error_ia): ?>
                    <div class="alert alert-danger">Error en IA: <?php echo htmlspecialchars($error_ia); ?></div>
                <?php endif; ?>

                <?php if ($resultado_ia): ?>
                    <div class="alert alert-info">
                        <strong>Resultado de la IA:</strong><br>
                        - Páginas: <?php echo $resultado_ia['paginas'] ?? 'N/A'; ?> (deben ser 8)<br>
                        - Fuente: <?php echo $resultado_ia['fuente'] ?? 'N/A'; ?><br>
                        - Explicación: <?php echo $resultado_ia['explicacion'] ?? 'N/A'; ?><br>
                        - Cumple: <?php echo $resultado_ia['cumple'] ? 'Sí' : 'No'; ?>
                        <?php if (!empty($resultado_ia['comentarios'])): ?>
                            <br><strong>Comentarios:</strong> <?php echo nl2br(htmlspecialchars($resultado_ia['comentarios'])); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cuestionario con tabla de criterios y detalles -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">Verificación de criterios y observaciones</div>
            <div class="card-body">
                <p>Marque los criterios que cumple y escriba detalles sobre lo que debe mejorar el autor (en caso de rechazo).</p>
                <form method="POST" id="formEvaluacion">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-criterios">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 5%">#</th>
                                    <th style="width: 40%">Criterio</th>
                                    <th style="width: 15%">¿Cumple?</th>
                                    <th style="width: 40%">Detalles / Observaciones (para el autor)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($criterios as $index => $criterio): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($criterio['texto']); ?></td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="criterios[]" value="<?php echo $criterio['id']; ?>" id="check_<?php echo $criterio['id']; ?>">
                                    </td>
                                    <td>
                                        <textarea class="form-control detalle-textarea" name="detalles[<?php echo $criterio['id']; ?>]" rows="2" placeholder="Opcional: Escriba aquí qué debe corregir el autor..."></textarea>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="button" id="btnMarcarTodos" class="btn btn-sm btn-secondary">Marcar todos los criterios</button>
                        <button type="button" id="btnDesmarcarTodos" class="btn btn-sm btn-secondary">Desmarcar todos</button>
                        <button type="button" id="btnMarcarIA" class="btn btn-sm btn-info" <?php echo (!$resultado_ia) ? 'disabled' : ''; ?>>Marcar según IA</button>
                        <span class="ms-3 text-muted" id="alertaDetalle" style="display:none;">
                            <i class="fas fa-exclamation-triangle"></i> Hay detalles escritos. Para aprobar, debe limpiar todos los detalles.
                        </span>
                    </div>

                    <div class="text-end mt-4">
                        <?php
                        // Verificar si hay algún detalle no vacío para deshabilitar aprobación
                        $hay_detalles = false;
                        // Esto se hace en cliente con JS; en servidor no es necesario.
                        ?>
                        <a href="aprobar.php?id=<?php echo $id; ?>" id="btnAprobar" class="btn btn-success btn-aprobar" onclick="return confirm('¿Aprobar este trabajo? Se mostrará en la agenda.')">Aprobar trabajo</a>
                        <button type="submit" name="rechazar" class="btn btn-danger" onclick="return confirm('¿Rechazar este trabajo? Se enviarán los detalles al autor.')">Rechazar trabajo</button>
                    </div>
                </form>
            </div>
        </div>

        <a href="evaluacion.php" class="btn btn-secondary">← Volver al listado</a>
    </div>

    <script>
        // Elementos
        const checkboxes = document.querySelectorAll('input[name="criterios[]"]');
        const textareas = document.querySelectorAll('.detalle-textarea');
        const btnAprobar = document.getElementById('btnAprobar');
        const alertaDetalle = document.getElementById('alertaDetalle');

        // Función para verificar si hay algún detalle no vacío
        function verificarDetalles() {
            let hayDetalle = false;
            textareas.forEach(ta => {
                if (ta.value.trim() !== '') {
                    hayDetalle = true;
                }
            });
            if (hayDetalle) {
                btnAprobar.classList.add('disabled');
                btnAprobar.style.pointerEvents = 'none';
                alertaDetalle.style.display = 'inline';
            } else {
                btnAprobar.classList.remove('disabled');
                btnAprobar.style.pointerEvents = 'auto';
                alertaDetalle.style.display = 'none';
            }
        }

        // Event listeners
        textareas.forEach(ta => ta.addEventListener('input', verificarDetalles));
        verificarDetalles(); // inicial

        // Marcar/desmarcar todos los checkboxes
        document.getElementById('btnMarcarTodos').addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = true);
        });
        document.getElementById('btnDesmarcarTodos').addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = false);
        });

        // Marcar según resultado de la IA
        document.getElementById('btnMarcarIA').addEventListener('click', () => {
            <?php if ($resultado_ia): ?>
                const ia = <?php echo json_encode($resultado_ia); ?>;
                if (ia.paginas === 8) document.getElementById('check_paginas').checked = true;
                if (ia.fuente && ia.fuente.toLowerCase().includes('arial')) document.getElementById('check_fuente').checked = true;
                if (ia.explicacion && ia.explicacion.toLowerCase().includes('suficiente')) document.getElementById('check_explicacion').checked = true;
                // Los demás criterios no se pueden determinar automáticamente
                alert('Se han marcado automáticamente los criterios que la IA pudo verificar. Revise el resto manualmente.');
            <?php else: ?>
                alert('Primero debe ejecutar la evaluación con IA.');
            <?php endif; ?>
        });
    </script>
</body>
</html>