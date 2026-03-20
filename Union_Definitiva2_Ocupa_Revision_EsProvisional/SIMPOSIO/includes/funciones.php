<?php
/**
 * Funciones auxiliares para el sistema de simposio
 */

/**
 * Devuelve la duración en minutos de un tipo de trabajo
 */
function duracion_tipo_trabajo($tipo) {
    $duraciones = [
        'cartel'    => 30,   // (ajustar según los trabajos)
        'ponencia'  => 30,
        'taller'    => 120,
        'prototipo' => 60
    ];
    return $duraciones[$tipo] ?? 30;
}

/**
 * Obtiene los horarios disponibles para un evento y una duración determinada
 * @param mysqli $conexion
 * @param int $id_evento
 * @param int $duracion_minutos
 * @return array Lista de horas disponibles (formato 'H:i')
 */
function obtener_horarios_disponibles($conexion, $id_evento, $duracion_minutos, $id_salon = null, $excluir_actividad = null) {
    // Obtener fecha y hora del evento
    $stmt = $conexion->prepare("SELECT fecha, hora_inicio, hora_fin FROM evento WHERE id_evento = ?");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    if (!$evento) return [];

    $fecha = $evento['fecha'];
    $hora_inicio_evento = strtotime($evento['hora_inicio']);
    $hora_fin_evento = strtotime($evento['hora_fin']);

    // Construir consulta para obtener actividades ocupadas
    if ($id_salon) {
        // Si hay salón, buscar en cualquier evento de la misma fecha con ese salón
        $sql = "SELECT hora_inicio, hora_fin FROM actividad_evento 
                WHERE fecha = ? AND id_salon = ?";
        $params = [$fecha, $id_salon];
        $types = "si";
    } else {
        // Si no hay salón, solo en el mismo evento
        $sql = "SELECT hora_inicio, hora_fin FROM actividad_evento 
                WHERE id_evento = ?";
        $params = [$id_evento];
        $types = "i";
    }

    // Excluir una actividad específica (para edición)
    if ($excluir_actividad) {
        $sql .= " AND id_actividad != ?";
        $params[] = $excluir_actividad;
        $types .= "i";
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $ocupados = [];
    while ($row = $result->fetch_assoc()) {
        $ocupados[] = [
            'inicio' => strtotime($row['hora_inicio']),
            'fin'    => strtotime($row['hora_fin'])
        ];
    }

    // Generar bloques de 30 minutos
    $disponibles = [];
    $hora_actual = $hora_inicio_evento;
    while ($hora_actual + $duracion_minutos * 60 <= $hora_fin_evento) {
        $inicio_bloque = $hora_actual;
        $fin_bloque = $hora_actual + $duracion_minutos * 60;

        $ocupado = false;
        foreach ($ocupados as $act) {
            if ($inicio_bloque < $act['fin'] && $fin_bloque > $act['inicio']) {
                $ocupado = true;
                break;
            }
        }

        if (!$ocupado) {
            $disponibles[] = date('H:i', $inicio_bloque);
        }

        $hora_actual = strtotime('+30 minutes', $hora_actual);
    }

    return $disponibles;
}

/**
 * Obtiene la lista de salones activos
 */
function obtener_salones($conexion) {
    $result = $conexion->query("SELECT id_salon, nombre, ubicacion, capacidad FROM salones WHERE activo = 1 ORDER BY nombre");
    $salones = [];
    while ($row = $result->fetch_assoc()) {
        $salones[] = $row;
    }
    return $salones;
}

/**
 * Mapea un tipo de trabajo a un id_tipo de actividad (según la tabla tipo_actividad)
 */
function tipo_trabajo_a_id_actividad($tipo) {
    $mapa = [
        'ponencia' => 1,   // asumiendo que id 1 es "Ponencia"
        'taller'   => 3,   // id 3 es "Taller"
        'cartel'   => 4, // los carteles quizás no van en agenda
        'prototipo' => 5
    ];
    return $mapa[$tipo] ?? null;
}

/**
 * Obtiene el ID específico (alumno, docente o empresa) a partir del usuario
 */
function obtener_id_especifico($usuario) {
    if ($usuario['tipo_usuario'] == 'alumno' && isset($usuario['id_alumno'])) {
        return ['tipo' => 'alumno', 'id' => $usuario['id_alumno']];
    } elseif ($usuario['tipo_usuario'] == 'docente' && isset($usuario['id_docente'])) {
        return ['tipo' => 'docente', 'id' => $usuario['id_docente']];
    } elseif ($usuario['tipo_usuario'] == 'empresa' && isset($usuario['id_empresa'])) {
        return ['tipo' => 'empresa', 'id' => $usuario['id_empresa']];
    }
    return null;
}
?>