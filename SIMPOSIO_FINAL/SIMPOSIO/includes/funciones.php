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
function obtener_horarios_disponibles($conexion, $id_evento, $duracion_minutos, $id_salon = null) {
    // Obtener fecha y hora del evento
    $stmt = $conexion->prepare("SELECT fecha, hora_inicio, hora_fin FROM evento WHERE id_evento = ?");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    if (!$evento) return [];

    $fecha = $evento['fecha'];
    $hora_inicio_evento = strtotime($evento['hora_inicio']);
    $hora_fin_evento = strtotime($evento['hora_fin']);

    $ocupados = [];
    if ($id_salon !== null) {
        // Verificar en todos los eventos de la misma fecha con el mismo salón
        $stmt = $conexion->prepare("SELECT hora_inicio, hora_fin FROM actividad_evento WHERE fecha = ? AND id_salon = ? AND visible = 1");
        $stmt->bind_param("si", $fecha, $id_salon);
    } else {
        // Solo en el evento actual
        $stmt = $conexion->prepare("SELECT hora_inicio, hora_fin FROM actividad_evento WHERE id_evento = ? AND visible = 1");
        $stmt->bind_param("i", $id_evento);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ocupados[] = [
            'inicio' => strtotime($row['hora_inicio']),
            'fin'    => strtotime($row['hora_fin'])
        ];
    }

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
function obtener_fecha_evento($conexion, $id_evento) {
    $stmt = $conexion->prepare("SELECT fecha FROM evento WHERE id_evento = ?");
    $stmt->bind_param("i", $id_evento);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['fecha'];
    }
    return null;
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

/*** Cuenta los trabajos pendientes de revisión para un docente*/
function contar_revisiones_docente($conexion, $id_docente) {
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM asignacion_revision WHERE id_docente = ? AND estado_revision = 'pendiente'");
    $stmt->bind_param("i", $id_docente);
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0];
}

function contar_pendientes_admin($conexion) {
    $result = $conexion->query("SELECT COUNT(*) FROM articulo WHERE estado = 'pendiente'");
    return $result->fetch_row()[0];
}

/**
 * Obtiene trabajos que tienen archivo PDF asociado (en actividad_evento) y están aprobados o pendientes
 */
function obtener_trabajos_con_pdf($conexion, $estado = null) {
    $sql = "
        SELECT a.id_articulo, a.titulo, a.tipo_trabajo, a.categoria, a.estado,
               ae.archivo_pdf, ae.id_actividad,
               u.nombre as autor_nombre, u.apellidos as autor_apellidos
        FROM articulo a
        JOIN actividad_evento ae ON a.id_articulo = ae.id_articulo
        JOIN usuario u ON a.id_usuario = u.id_usuario
        WHERE ae.archivo_pdf IS NOT NULL AND ae.archivo_pdf != ''
    ";
    if ($estado) {
        $sql .= " AND a.estado = '" . $conexion->real_escape_string($estado) . "'";
    }
    $sql .= " ORDER BY a.fecha_registro DESC";
    return $conexion->query($sql);
}

/**
 * Extrae texto de un archivo PDF usando una librería externa (requiere smalot/pdfparser)
 * Instalación: composer require smalot/pdfparser
 * Si no se puede instalar, se usará un método alternativo con IA directa (subida del archivo)
 */
function extraer_texto_pdf($ruta_pdf) {
    if (!file_exists($ruta_pdf)) {
        return false;
    }
    // Intentar usar PDFParser si está disponible
    if (class_exists('\\Smalot\\PdfParser\\Parser')) {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($ruta_pdf);
        return $pdf->getText();
    }
    // Fallback: leer con file_get_contents (no recomendado, pero funciona para texto básico)
    return file_get_contents($ruta_pdf);
}

/**
 * Envía el PDF a la API de Gemini para evaluación
 * @param string $ruta_pdf Ruta local del archivo
 * @param string $api_key Clave de API de Gemini
 * @return array Resultado con 'cumple', 'paginas', 'fuente', 'explicacion', 'comentarios'
 */
function evaluar_extenso_con_gemini($ruta_pdf, $api_key) {
    $prompt = "Evalúa el siguiente documento académico (extenso de proyecto) según estos criterios:
1. Número de páginas: debe ser exactamente 8 (ocho) cuartillas.
2. Tipo de letra: debe ser Arial (o similar, claramente legible y estándar).
3. Explicación del proyecto: debe contener una descripción clara y completa del proyecto.

Analiza el contenido del PDF. Devuelve un JSON con la siguiente estructura:
{
  \"cumple\": true/false,
  \"paginas\": numero_de_paginas,
  \"fuente\": \"Arial\" o \"otra\",
  \"explicacion\": \"suficiente\" o \"insuficiente\",
  \"comentarios\": \"explicación detallada de por qué cumple o no\"
}

Contenido del PDF:
";

    // Leer el PDF como base64 (Gemini acepta archivos en base64)
    $pdf_content = base64_encode(file_get_contents($ruta_pdf));
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => 'application/pdf',
                            'data' => $pdf_content
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['error' => "Error en la API: HTTP $http_code", 'respuesta' => $response];
    }
    
    $result = json_decode($response, true);
    $texto = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Extraer JSON del texto
    preg_match('/\{.*\}/s', $texto, $matches);
    if (empty($matches)) {
        return ['error' => 'No se pudo extraer JSON de la respuesta', 'raw' => $texto];
    }
    
    return json_decode($matches[0], true);
}

// ==================== FUNCIONES DE PATROCINIO ====================
/**
 * Verifica si una empresa ya ha solicitado patrocinio para un proyecto específico.
 */
function ya_solicito_patrocinio($conexion, $id_articulo, $id_empresa) {
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM patrocinios WHERE id_articulo = ? AND id_empresa = ?");
    $stmt->bind_param("ii", $id_articulo, $id_empresa);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    return $count > 0;
}
/**
 * Obtiene el estado de una solicitud de patrocinio para un proyecto y empresa.
 */
function obtener_estado_patrocinio($conexion, $id_articulo, $id_empresa) {
    $stmt = $conexion->prepare("SELECT estado FROM patrocinios WHERE id_articulo = ? AND id_empresa = ?");
    $stmt->bind_param("ii", $id_articulo, $id_empresa);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    return $resultado ? $resultado['estado'] : null;
}
/**
 * Obtiene todas las solicitudes de patrocinio hechas por una empresa.
 */
function obtener_solicitudes_empresa($conexion, $id_empresa) {
    $sql = "SELECT p.*, a.titulo as proyecto_titulo, u.nombre as autor_nombre, u.apellidos as autor_apellidos
            FROM patrocinios p
            JOIN articulo a ON p.id_articulo = a.id_articulo
            JOIN usuario u ON a.id_usuario = u.id_usuario
            WHERE p.id_empresa = ?
            ORDER BY p.fecha_solicitud DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_empresa);
    $stmt->execute();
    return $stmt->get_result();
}
/**
 * Obtiene todas las solicitudes de patrocinio recibidas para los proyectos de un usuario (alumno/docente).
 */
function obtener_solicitudes_recibidas($conexion, $id_usuario) {
    $sql = "SELECT p.*, a.titulo as proyecto_titulo, e.nombre_empresa, u_e.nombre as empresa_nombre
            FROM patrocinios p
            JOIN articulo a ON p.id_articulo = a.id_articulo
            JOIN empresa e ON p.id_empresa = e.id_empresa
            JOIN usuario u_e ON e.id_usuario = u_e.id_usuario
            WHERE a.id_usuario = ? OR a.id_articulo IN (
                SELECT id_articulo FROM articulo_alumno aa JOIN alumno al ON aa.id_alumno = al.id_alumno WHERE al.id_usuario = ?
            ) OR a.id_articulo IN (
                SELECT id_articulo FROM articulo_docente ad JOIN docente d ON ad.id_docente = d.id_docente WHERE d.id_usuario = ?
            )
            ORDER BY p.fecha_solicitud DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iii", $id_usuario, $id_usuario, $id_usuario);
    $stmt->execute();
    return $stmt->get_result();
}
/**
 * Obtiene los patrocinadores de un proyecto
 */
function obtener_patrocinadores_proyecto($conexion, $id_articulo) {
    $sql = "SELECT p.*, e.nombre_empresa, u.nombre, u.apellidos, u.correo
            FROM patrocinios p
            JOIN empresa e ON p.id_empresa = e.id_empresa
            JOIN usuario u ON e.id_usuario = u.id_usuario
            WHERE p.id_articulo = ? AND p.estado = 'aceptado'
            ORDER BY p.fecha_respuesta DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    return $stmt->get_result();
}
/**
 * Cuenta cuántos patrocinadores tiene un proyecto
 */
function contar_patrocinadores_proyecto($conexion, $id_articulo) {
    $sql = "SELECT COUNT(*) FROM patrocinios WHERE id_articulo = ? AND estado = 'aceptado'";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_articulo);
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0];
}

// Función auxiliar para tiempo relativo
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    
    $string = array(
        'y' => 'año',
        'm' => 'mes',
        'w' => 'semana',
        'd' => 'día',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' atrás' : 'justo ahora';
}
/**
 * Cuenta cuántas solicitudes de patrocinio ha hecho una empresa para un proyecto
 * (incluye todos los estados: pendiente, aceptado, rechazado)
 */
function contar_intentos_patrocinio($conexion, $id_articulo, $id_empresa) {
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM patrocinios WHERE id_articulo = ? AND id_empresa = ?");
    $stmt->bind_param("ii", $id_articulo, $id_empresa);
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0];
}
/**
 * Verifica si una empresa ya agotó sus intentos de patrocinio para un proyecto
 * Retorna true si ya no puede reenviar (llegó al límite de 2 intentos)
 */
function intentos_patrocinio_agotados($conexion, $id_articulo, $id_empresa) {
    $intentos = contar_intentos_patrocinio($conexion, $id_articulo, $id_empresa);
    // Máximo 2 intentos: 1 solicitud inicial + 1 reenvío
    return $intentos >= 2;
}
?>