<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/config.php'; // para la API key

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

// Definir preguntas del cuestionario
$preguntas = [
    'paginas' => 'El documento tiene exactamente 8 páginas (cuartillas).',
    'fuente' => 'La fuente utilizada es Arial (o similar, legible).',
    'explicacion' => 'Se explica claramente el proyecto, sus objetivos y alcance.',
    'estructura' => 'El documento sigue una estructura académica: introducción, desarrollo, conclusiones.',
    'ortografia' => 'La redacción es clara y sin errores ortográficos graves.'
];

// Procesar evaluación con IA
$resultado_ia = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['evaluar_ia'])) {
    $api_key = GEMINI_API_KEY;
    $ruta_pdf = '../../' . $trabajo['archivo_pdf'];
    $resultado_ia = evaluar_extenso_con_gemini($ruta_pdf, $api_key);
}

// Procesar aprobación/rechazo (desde el formulario de cuestionario)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    if (in_array($nuevo_estado, ['aprobado', 'rechazado'])) {
        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare("UPDATE articulo SET estado = ? WHERE id_articulo = ?");
            $stmt->bind_param("si", $nuevo_estado, $id);
            $stmt->execute();
            if ($nuevo_estado == 'rechazado') {
                $stmt2 = $conexion->prepare("UPDATE actividad_evento SET visible = 0 WHERE id_articulo = ?");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
            } else {
                // Si se aprueba, asegurar visibilidad
                $stmt2 = $conexion->prepare("UPDATE actividad_evento SET visible = 1 WHERE id_articulo = ?");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
            }
            $conexion->commit();
            header("Location: index.php?mensaje=actualizado");
            exit;
        } catch (Exception $e) {
            $conexion->rollback();
            $error = "Error al cambiar estado: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluar extenso - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../Css/admin.css">
    <style>
        .pdf-viewer {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .cuestionario {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .form-check {
            margin-bottom: 10px;
        }
        .btn-aprobar, .btn-rechazar {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar_admin.php'; ?>
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
                <div class="text-center">
                    <a href="../../<?php echo $trabajo['archivo_pdf']; ?>" target="_blank" class="btn btn-sm btn-info">Abrir en nueva pestaña</a>
                </div>
            </div>
        </div>

        <!-- Cuestionario y evaluación con IA -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Evaluación con IA y criterios</div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <button type="submit" name="evaluar_ia" class="btn btn-primary">
                        <i class="fas fa-brain"></i> Evaluar con IA
                    </button>
                </form>

                <?php if ($resultado_ia && !isset($resultado_ia['error'])): ?>
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
                <?php elseif ($resultado_ia && isset($resultado_ia['error'])): ?>
                    <div class="alert alert-danger">Error en IA: <?php echo $resultado_ia['error']; ?></div>
                <?php endif; ?>

                <form method="POST" id="formEvaluacion">
                    <h5>Cuestionario de verificación</h5>
                    <p>Marque los criterios que cumple el documento:</p>
                    <?php foreach ($preguntas as $clave => $texto): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="criterios[]" value="<?php echo $clave; ?>" id="check_<?php echo $clave; ?>">
                        <label class="form-check-label" for="check_<?php echo $clave; ?>">
                            <?php echo $texto; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>

                    <div class="mt-3">
                        <button type="button" id="btnMarcarTodos" class="btn btn-sm btn-secondary">Marcar todos</button>
                        <button type="button" id="btnDesmarcarTodos" class="btn btn-sm btn-secondary">Desmarcar todos</button>
                        <button type="button" id="btnMarcarIA" class="btn btn-sm btn-info" <?php echo (!$resultado_ia || isset($resultado_ia['error'])) ? 'disabled' : ''; ?>>Marcar según IA</button>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" name="cambiar_estado" value="aprobado" class="btn btn-success btn-aprobar" disabled>Aprobar trabajo</button>
                        <button type="submit" name="cambiar_estado" value="rechazado" class="btn btn-danger btn-rechazar" disabled>Rechazar trabajo</button>
                        <input type="hidden" name="nuevo_estado" id="nuevo_estado" value="">
                    </div>
                </form>
            </div>
        </div>

        <a href="evaluacion.php" class="btn btn-secondary">← Volver al listado</a>
    </div>

    <script>
        // Habilitar botones solo si todos los checkboxes están marcados
        const checkboxes = document.querySelectorAll('input[name="criterios[]"]');
        const btnAprobar = document.querySelector('.btn-aprobar');
        const btnRechazar = document.querySelector('.btn-rechazar');
        const inputEstado = document.getElementById('nuevo_estado');

        function actualizarBotones() {
            const todosMarcados = Array.from(checkboxes).every(cb => cb.checked);
            btnAprobar.disabled = !todosMarcados;
            btnRechazar.disabled = !todosMarcados;
        }

        checkboxes.forEach(cb => cb.addEventListener('change', actualizarBotones));

        // Marcar/desmarcar todos
        document.getElementById('btnMarcarTodos').addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = true);
            actualizarBotones();
        });
        document.getElementById('btnDesmarcarTodos').addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = false);
            actualizarBotones();
        });

        // Marcar según resultado de la IA (si existe)
        document.getElementById('btnMarcarIA').addEventListener('click', () => {
            <?php if ($resultado_ia && !isset($resultado_ia['error'])): ?>
                // Mapeo de criterios según resultado de IA
                const ia = <?php echo json_encode($resultado_ia); ?>;
                if (ia.paginas === 8) document.getElementById('check_paginas').checked = true;
                if (ia.fuente && ia.fuente.toLowerCase().includes('arial')) document.getElementById('check_fuente').checked = true;
                if (ia.explicacion && ia.explicacion.toLowerCase().includes('suficiente')) document.getElementById('check_explicacion').checked = true;
                // Para estructura y ortografía, la IA no puede determinarlo automáticamente, se deja manual
                actualizarBotones();
            <?php else: ?>
                alert('Primero debe ejecutar la evaluación con IA.');
            <?php endif; ?>
        });

        // Al hacer clic en aprobar/rechazar, asignar el valor al input oculto
        document.querySelector('.btn-aprobar').addEventListener('click', (e) => {
            e.preventDefault();
            inputEstado.value = 'aprobado';
            document.getElementById('formEvaluacion').submit();
        });
        document.querySelector('.btn-rechazar').addEventListener('click', (e) => {
            e.preventDefault();
            inputEstado.value = 'rechazado';
            document.getElementById('formEvaluacion').submit();
        });
    </script>
</body>
</html>