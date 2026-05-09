<?php

// ========== FUNCIONES BASE ==========

function crear_notificacion($conexion, $id_usuario, $tipo, $titulo, $mensaje, $icono = null, $enlace = null) {
    $stmt = $conexion->prepare("INSERT INTO notificaciones (id_usuario, tipo, titulo, mensaje, icono, enlace) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $id_usuario, $tipo, $titulo, $mensaje, $icono, $enlace);
    return $stmt->execute();
}

function contar_notificaciones_no_leidas($conexion, $id_usuario) {
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario = ? AND leida = 0");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}

function obtener_notificaciones_no_leidas($conexion, $id_usuario, $limite = 5) {
    $stmt = $conexion->prepare("SELECT * FROM notificaciones WHERE id_usuario = ? AND leida = 0 ORDER BY fecha_creacion DESC LIMIT ?");
    $stmt->bind_param("ii", $id_usuario, $limite);
    $stmt->execute();
    return $stmt->get_result();
}

function obtener_notificaciones($conexion, $id_usuario, $offset = 0, $limite = 10) {
    $stmt = $conexion->prepare("SELECT * FROM notificaciones WHERE id_usuario = ? ORDER BY fecha_creacion DESC LIMIT ?, ?");
    $stmt->bind_param("iii", $id_usuario, $offset, $limite);
    $stmt->execute();
    return $stmt->get_result();
}

function marcar_notificacion_leida($conexion, $id_notificacion, $id_usuario) {
    $stmt = $conexion->prepare("UPDATE notificaciones SET leida = 1, fecha_lectura = NOW() WHERE id_notificacion = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id_notificacion, $id_usuario);
    return $stmt->execute();
}

function marcar_todas_notificaciones_leidas($conexion, $id_usuario) {
    $stmt = $conexion->prepare("UPDATE notificaciones SET leida = 1, fecha_lectura = NOW() WHERE id_usuario = ? AND leida = 0");
    $stmt->bind_param("i", $id_usuario);
    return $stmt->execute();
}

function eliminar_notificacion($conexion, $id_notificacion, $id_usuario) {
    $stmt = $conexion->prepare("DELETE FROM notificaciones WHERE id_notificacion = ? AND id_usuario = ?");
    $stmt->bind_param("ii", $id_notificacion, $id_usuario);
    return $stmt->execute();
}

/**
 * Obtiene el id_usuario del autor principal de un artículo,
 * buscando primero en articulo_alumno, luego en articulo_docente,
 * y finalmente en articulo.id_usuario
 */
function obtener_autor_articulo($conexion, $id_articulo) {
    // 1. Buscar en articulo_alumno (alumnos con rol 'autor')
    $stmt = $conexion->prepare("
        SELECT u.id_usuario 
        FROM articulo_alumno aa 
        JOIN alumno a ON aa.id_alumno = a.id_alumno 
        JOIN usuario u ON a.id_usuario = u.id_usuario 
        WHERE aa.id_articulo = ? AND aa.rol = 'autor' 
        LIMIT 1
    ");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) return $result['id_usuario'];
    
    // 2. Buscar en articulo_docente (docentes)
    $stmt = $conexion->prepare("
        SELECT u.id_usuario 
        FROM articulo_docente ad 
        JOIN docente d ON ad.id_docente = d.id_docente 
        JOIN usuario u ON d.id_usuario = u.id_usuario 
        WHERE ad.id_articulo = ? 
        LIMIT 1
    ");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) return $result['id_usuario'];
    
    // 3. Buscar en articulo.id_usuario (empresas o respaldo)
    $stmt = $conexion->prepare("SELECT id_usuario FROM articulo WHERE id_articulo = ?");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && !empty($result['id_usuario'])) return $result['id_usuario'];
    
    return null;
}


// ========== NOTIFICACIÓN DE PATROCINIO (SOLO AUTOR PRINCIPAL) ==========

function notificar_nuevo_patrocinio($conexion, $id_articulo, $id_empresa) {
    $autor_id = null;
    $titulo_proyecto = null;
    
    // 1. BUSCAR AUTOR ALUMNO (rol = 'autor')
    $stmt = $conexion->prepare("
        SELECT u.id_usuario, a.titulo
        FROM articulo a
        JOIN articulo_alumno aa ON a.id_articulo = aa.id_articulo
        JOIN alumno al ON aa.id_alumno = al.id_alumno
        JOIN usuario u ON al.id_usuario = u.id_usuario
        WHERE a.id_articulo = ? AND aa.rol = 'autor'
        LIMIT 1
    ");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $autor_id = $row['id_usuario'];
        $titulo_proyecto = $row['titulo'];
    }
    
    // 2. BUSCAR AUTOR DOCENTE (si no se encontró alumno)
    if (!$autor_id) {
        $stmt = $conexion->prepare("
            SELECT u.id_usuario, a.titulo
            FROM articulo a
            JOIN articulo_docente ad ON a.id_articulo = ad.id_articulo
            JOIN docente d ON ad.id_docente = d.id_docente
            JOIN usuario u ON d.id_usuario = u.id_usuario
            WHERE a.id_articulo = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $id_articulo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $autor_id = $row['id_usuario'];
            $titulo_proyecto = $row['titulo'];
        }
    }
    
    // 3. BUSCAR EN articulo.id_usuario (respaldo para empresas)
    if (!$autor_id) {
        $stmt = $conexion->prepare("
            SELECT u.id_usuario, a.titulo 
            FROM articulo a 
            JOIN usuario u ON a.id_usuario = u.id_usuario
            WHERE a.id_articulo = ?
        ");
        $stmt->bind_param("i", $id_articulo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $autor_id = $row['id_usuario'];
            $titulo_proyecto = $row['titulo'];
        }
    }
    
    // Si no se encontró ningún autor, salir
    if (!$autor_id) {
        error_log("❌ No se encontró autor para el artículo $id_articulo");
        return false;
    }
    
    // Obtener nombre de la empresa
    $stmt = $conexion->prepare("
        SELECT e.nombre_empresa, u.nombre, u.apellidos
        FROM empresa e
        JOIN usuario u ON e.id_usuario = u.id_usuario
        WHERE e.id_empresa = ?
    ");
    $stmt->bind_param("i", $id_empresa);
    $stmt->execute();
    $empresa = $stmt->get_result()->fetch_assoc();
    
    if (!$empresa) {
        error_log("❌ No se encontró empresa con id $id_empresa");
        return false;
    }
    
    // Debug
    error_log("✅ Creando notificación para autor ID: $autor_id, Proyecto: $titulo_proyecto, Empresa: {$empresa['nombre_empresa']}");
    
    // Crear la notificación para el autor REAL
    $resultado = crear_notificacion(
        $conexion,
        $autor_id,
        'info',
        '💼 Nueva solicitud de patrocinio',
        "La empresa '{$empresa['nombre_empresa']}' quiere patrocinar tu proyecto '{$titulo_proyecto}'. Revisa la solicitud en 'Mis Proyectos'.",
        'fa-hand-holding-usd',
        'mis_proyectos.php'
    );
    
    if ($resultado) {
        error_log("✅ Notificación creada exitosamente para usuario $autor_id");
    } else {
        error_log("❌ Error al crear notificación para usuario $autor_id");
    }
    
    return $resultado;
}


// ========== NOTIFICACIÓN PATROCIONIO ==========

function notificar_respuesta_patrocinio($conexion, $id_patrocinio, $estado, $comentarios = null) {
    $stmt = $conexion->prepare("
        SELECT p.id_empresa, a.titulo as proyecto_titulo, u.id_usuario, u.nombre, u.apellidos
        FROM patrocinios p
        JOIN articulo a ON p.id_articulo = a.id_articulo
        JOIN empresa e ON p.id_empresa = e.id_empresa
        JOIN usuario u ON e.id_usuario = u.id_usuario
        WHERE p.id_patrocinio = ?
    ");
    $stmt->bind_param("i", $id_patrocinio);
    $stmt->execute();
    $patrocinio = $stmt->get_result()->fetch_assoc();
    
    if (!$patrocinio) {
        error_log("❌ No se encontró patrocinio $id_patrocinio");
        return false;
    }
    
    $estado_texto = $estado == 'aceptado' ? 'ACEPTADA' : 'RECHAZADA';
    $tipo = $estado == 'aceptado' ? 'success' : 'danger';
    $mensaje = "Tu solicitud para patrocinar '{$patrocinio['proyecto_titulo']}' ha sido {$estado_texto}.";
    
    if ($comentarios) {
        $mensaje .= " Comentario del autor: \"{$comentarios}\"";
    }
    
    error_log("✅ Notificando a empresa ID: {$patrocinio['id_usuario']} sobre patrocinio $id_patrocinio");
    
    return crear_notificacion(
        $conexion,
        $patrocinio['id_usuario'],
        $tipo,
        "Patrocinio {$estado_texto}",
        $mensaje,
        $estado == 'aceptado' ? 'fa-check-circle' : 'fa-times-circle',
        'patrocinar_proyectos.php?ver=solicitudes'
    );
}


// ========== NOTIFICACIONES BÁSICAS PARA ALUMNOS Y DOCENTES ==========

function notificar_trabajo_registrado($conexion, $id_articulo, $id_usuario_autor) {
    $stmt = $conexion->prepare("SELECT titulo FROM articulo WHERE id_articulo = ?");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $articulo = $stmt->get_result()->fetch_assoc();
    
    return crear_notificacion(
        $conexion,
        $id_usuario_autor,
        'success',
        'Trabajo registrado',
        "Tu trabajo '{$articulo['titulo']}' ha sido registrado correctamente.",
        'fa-check-circle',
        "ver_proyecto.php?id=$id_articulo"
    );
}

function notificar_trabajo_aprobado($conexion, $id_articulo, $id_usuario_autor) {
    $stmt = $conexion->prepare("SELECT titulo FROM articulo WHERE id_articulo = ?");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $articulo = $stmt->get_result()->fetch_assoc();
    
    return crear_notificacion(
        $conexion,
        $id_usuario_autor,
        'success',
        'Trabajo aprobado',
        "Tu trabajo '{$articulo['titulo']}' ha sido APROBADO.",
        'fa-check-circle',
        "ver_proyecto.php?id=$id_articulo"
    );
}

function notificar_trabajo_rechazado($conexion, $id_articulo, $id_usuario_autor, $comentarios = null) {
    $stmt = $conexion->prepare("SELECT titulo FROM articulo WHERE id_articulo = ?");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $articulo = $stmt->get_result()->fetch_assoc();
    
    $mensaje = "Tu trabajo '{$articulo['titulo']}' ha sido RECHAZADO.";
    if ($comentarios) {
        $mensaje .= " Motivo: {$comentarios}";
    }
    
    return crear_notificacion(
        $conexion,
        $id_usuario_autor,
        'danger',
        'Trabajo rechazado',
        $mensaje,
        'fa-times-circle',
        "ver_proyecto.php?id=$id_articulo"
    );
}

function notificar_horario_asignado($conexion, $id_articulo, $id_usuario_autor, $fecha, $hora, $salon) {
    $stmt = $conexion->prepare("SELECT titulo FROM articulo WHERE id_articulo = ?");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $articulo = $stmt->get_result()->fetch_assoc();
    
    $fecha_formateada = date('d/m/Y', strtotime($fecha));
    
    return crear_notificacion(
        $conexion,
        $id_usuario_autor,
        'success',
        'Horario asignado',
        "Tu trabajo '{$articulo['titulo']}' ha sido programado para el {$fecha_formateada} a las {$hora} en {$salon}.",
        'fa-calendar-check',
        "ver_proyecto.php?id=$id_articulo"
    );
}

// ========== NOTIFICACIÓN DE EDICIÓN DE PROYECTO ==========

function notificar_edicion_proyecto($conexion, $id_articulo, $id_usuario_editor, $descripcion_cambios = null) {
    // Obtener título del proyecto
    $stmt = $conexion->prepare("SELECT titulo FROM articulo WHERE id_articulo = ?");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $articulo = $stmt->get_result()->fetch_assoc();
    
    if (!$articulo) return false;
    
    $mensaje = "El proyecto '{$articulo['titulo']}' ha sido editado.";
    if ($descripcion_cambios) {
        $mensaje .= " Cambios: {$descripcion_cambios}";
    }
    
    // Notificar a todos los participantes excepto al editor
    $participantes = [];
    
    // Obtener alumnos coautores
    $stmt = $conexion->prepare("
        SELECT u.id_usuario 
        FROM articulo_alumno aa 
        JOIN alumno a ON aa.id_alumno = a.id_alumno 
        JOIN usuario u ON a.id_usuario = u.id_usuario 
        WHERE aa.id_articulo = ? AND u.id_usuario != ?
    ");
    $stmt->bind_param("ii", $id_articulo, $id_usuario_editor);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $participantes[] = $row['id_usuario'];
    }
    
    // Obtener docentes
    $stmt = $conexion->prepare("
        SELECT u.id_usuario 
        FROM articulo_docente ad 
        JOIN docente d ON ad.id_docente = d.id_docente 
        JOIN usuario u ON d.id_usuario = u.id_usuario 
        WHERE ad.id_articulo = ? AND u.id_usuario != ?
    ");
    $stmt->bind_param("ii", $id_articulo, $id_usuario_editor);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['id_usuario'], $participantes)) {
            $participantes[] = $row['id_usuario'];
        }
    }
    
    // Crear notificación para cada participante
    foreach ($participantes as $id_participante) {
        crear_notificacion(
            $conexion,
            $id_participante,
            'info',
            '📝 Proyecto actualizado',
            $mensaje,
            'fa-edit',
            "ver_proyecto.php?id={$id_articulo}"
        );
    }
    
    return true;
}


// ========== NOTIFICACIÓN DE ELIMINACIÓN DE PROYECTO ==========

function notificar_eliminacion_proyecto($conexion, $id_articulo, $id_usuario_propietario, $titulo_proyecto = null) {
    // Si no se proporciona el título, obtenerlo
    if (!$titulo_proyecto) {
        $stmt = $conexion->prepare("SELECT titulo FROM articulo WHERE id_articulo = ?");
        $stmt->bind_param("i", $id_articulo);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $titulo_proyecto = $result['titulo'] ?? 'Sin título';
    }
    
    $participantes = [];
    
    // Obtener alumnos coautores
    $stmt = $conexion->prepare("
        SELECT u.id_usuario 
        FROM articulo_alumno aa 
        JOIN alumno a ON aa.id_alumno = a.id_alumno 
        JOIN usuario u ON a.id_usuario = u.id_usuario 
        WHERE aa.id_articulo = ? AND u.id_usuario != ?
    ");
    $stmt->bind_param("ii", $id_articulo, $id_usuario_propietario);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $participantes[] = $row['id_usuario'];
    }
    
    // Obtener docentes
    $stmt = $conexion->prepare("
        SELECT u.id_usuario 
        FROM articulo_docente ad 
        JOIN docente d ON ad.id_docente = d.id_docente 
        JOIN usuario u ON d.id_usuario = u.id_usuario 
        WHERE ad.id_articulo = ? AND u.id_usuario != ?
    ");
    $stmt->bind_param("ii", $id_articulo, $id_usuario_propietario);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['id_usuario'], $participantes)) {
            $participantes[] = $row['id_usuario'];
        }
    }
    
    // Obtener patrocinadores
    $stmt = $conexion->prepare("
        SELECT u.id_usuario 
        FROM patrocinios p 
        JOIN empresa e ON p.id_empresa = e.id_empresa 
        JOIN usuario u ON e.id_usuario = u.id_usuario 
        WHERE p.id_articulo = ? AND p.estado = 'aceptado'
    ");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['id_usuario'], $participantes)) {
            $participantes[] = $row['id_usuario'];
        }
    }
    
    // Crear notificación para cada participante
    foreach ($participantes as $id_participante) {
        crear_notificacion(
            $conexion,
            $id_participante,
            'danger',
            '🗑️ Proyecto eliminado',
            "El proyecto '{$titulo_proyecto}' ha sido eliminado por el autor.",
            'fa-trash-alt',
            'mis_proyectos.php'
        );
    }
    
    return true;
}


// ========== NOTIFICACIÓN DE DEVOLUCIÓN CON CORRECCIONES ==========

function notificar_devolucion_correcciones($conexion, $id_articulo, $id_docente, $comentarios = null) {
    // Obtener id_usuario del autor del proyecto
    $stmt = $conexion->prepare("
        SELECT a.titulo, a.id_usuario 
        FROM articulo a 
        WHERE a.id_articulo = ?
    ");
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) return false;
    
    // Obtener nombre del docente
    $stmt_doc = $conexion->prepare("
        SELECT u.nombre, u.apellidos 
        FROM docente d 
        JOIN usuario u ON d.id_usuario = u.id_usuario 
        WHERE d.id_docente = ?
    ");
    $stmt_doc->bind_param("i", $id_docente);
    $stmt_doc->execute();
    $docente = $stmt_doc->get_result()->fetch_assoc();
    $nombre_docente = $docente ? $docente['nombre'] . ' ' . $docente['apellidos'] : 'El revisor';
    
    $mensaje = "Tu trabajo '{$result['titulo']}' ha sido devuelto por {$nombre_docente} para realizar correcciones.";
    if ($comentarios) {
        $mensaje .= " Comentarios: \"{$comentarios}\"";
    }
    
    // También notificar a los coautores
    $participantes = [];
    $stmt_co = $conexion->prepare("
        SELECT u.id_usuario 
        FROM articulo_alumno aa 
        JOIN alumno a ON aa.id_alumno = a.id_alumno 
        JOIN usuario u ON a.id_usuario = u.id_usuario 
        WHERE aa.id_articulo = ? AND u.id_usuario != ?
    ");
    $stmt_co->bind_param("ii", $id_articulo, $result['id_usuario']);
    $stmt_co->execute();
    $result_co = $stmt_co->get_result();
    while ($row = $result_co->fetch_assoc()) {
        $participantes[] = $row['id_usuario'];
    }
    
    // Notificar al autor principal
    crear_notificacion(
        $conexion,
        $result['id_usuario'],
        'warning',
        '📋 Trabajo devuelto para correcciones',
        $mensaje,
        'fa-undo',
        "ver_proyecto.php?id={$id_articulo}"
    );
    
    // Notificar a coautores
    foreach ($participantes as $id_part) {
        crear_notificacion(
            $conexion,
            $id_part,
            'warning',
            '📋 Trabajo devuelto para correcciones',
            "El proyecto '{$result['titulo']}' requiere correcciones.",
            'fa-undo',
            "ver_proyecto.php?id={$id_articulo}"
        );
    }
    
    return true;
}

?>