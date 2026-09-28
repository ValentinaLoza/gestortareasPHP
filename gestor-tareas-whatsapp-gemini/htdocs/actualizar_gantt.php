<?php

session_start();

header("Content-Type: application/json");

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "No autorizado."
    ]);
    exit;
}

require_once "config/database.php";
require_once "services/TareaEstadoService.php";

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode([
        "success" => false,
        "message" => "Datos inválidos."
    ]);
    exit;
}

$tareaId = $input["id"] ?? null;
$fechaInicio = $input["fecha_inicio"] ?? null;
$fechaVencimiento = $input["fecha_vencimiento"] ?? null;
$progreso = $input["progreso"] ?? null;

if (!$tareaId) {
    echo json_encode([
        "success" => false,
        "message" => "ID de tarea faltante."
    ]);
    exit;
}

try {

    /*
        ACTUALIZAR FECHAS DESDE EL GANTT
    */
    if ($fechaInicio && $fechaVencimiento) {
        if ($fechaInicio > $fechaVencimiento) {
            echo json_encode([
                "success" => false,
                "message" => "La fecha de inicio no puede ser posterior al vencimiento."
            ]);
            exit;
        }

        $sql = "UPDATE tareas
                SET fecha_inicio = :fecha_inicio,
                    fecha_vencimiento = :fecha_vencimiento
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":fecha_inicio" => $fechaInicio,
            ":fecha_vencimiento" => $fechaVencimiento,
            ":id" => $tareaId
        ]);
    }

    /*
        ACTUALIZAR PROGRESO Y ESTADO DESDE EL GANTT
    */
    if ($progreso !== null) {
        TareaEstadoService::actualizarProgreso($pdo, $tareaId, $progreso);
    }

    echo json_encode([
        "success" => true,
        "message" => "Tarea actualizada correctamente."
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al actualizar tarea: " . $e->getMessage()
    ]);
}
