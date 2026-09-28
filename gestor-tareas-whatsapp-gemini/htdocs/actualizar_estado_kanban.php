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

$tareaId = $input["tarea_id"] ?? null;
$estadoId = $input["estado_id"] ?? null;

if (!$tareaId || !$estadoId) {
    echo json_encode([
        "success" => false,
        "message" => "Datos incompletos."
    ]);
    exit;
}

try {
    $datos = TareaEstadoService::actualizarEstado($pdo, $tareaId, $estadoId);

    echo json_encode([
        "success" => true,
        "message" => "Estado actualizado correctamente.",
        "progreso" => $datos["progreso"],
        "estado" => $datos["estado"]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error al actualizar estado: " . $e->getMessage()
    ]);
}
