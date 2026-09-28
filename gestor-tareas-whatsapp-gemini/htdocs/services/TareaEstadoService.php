<?php

class TareaEstadoService
{
    public static function sincronizarDesdeProgreso($pdo, $progreso)
    {
        $progreso = self::normalizarProgreso($progreso);

        if ($progreso === 100) {
            $nombreEstado = "Finalizada";
        } elseif ($progreso > 0) {
            $nombreEstado = "En proceso";
        } else {
            $nombreEstado = "Pendiente";
        }

        $estadoId = self::obtenerEstadoIdPorNombre($pdo, $nombreEstado);

        return [
            "estado_id" => $estadoId,
            "progreso" => $progreso,
            "finalizada" => $nombreEstado === "Finalizada"
        ];
    }

    public static function sincronizarDesdeEstado($pdo, $estadoId, $progresoActual)
    {
        $estado = self::obtenerEstadoPorId($pdo, $estadoId);
        $nombreEstado = strtolower(trim($estado["nombre"]));
        $progreso = self::normalizarProgreso($progresoActual);

        if ($nombreEstado === "finalizada") {
            $progreso = 100;
        } elseif ($nombreEstado === "pendiente") {
            $progreso = 0;
        } else {
            if ($progreso === 100) {
                $progreso = 99;
            }

            if ($nombreEstado === "en proceso" && $progreso === 0) {
                $progreso = 10;
            }
        }

        return [
            "estado_id" => (int)$estado["id"],
            "estado" => $estado["nombre"],
            "progreso" => $progreso,
            "finalizada" => $nombreEstado === "finalizada"
        ];
    }

    public static function sincronizarFormulario($pdo, $estadoId, $progreso)
    {
        $estado = self::obtenerEstadoPorId($pdo, $estadoId);
        $nombreEstado = strtolower(trim($estado["nombre"]));

        if ($nombreEstado === "finalizada" || $nombreEstado === "cancelada") {
            return self::sincronizarDesdeEstado($pdo, $estadoId, $progreso);
        }

        return self::sincronizarDesdeProgreso($pdo, $progreso);
    }

    public static function actualizarProgreso($pdo, $tareaId, $progreso)
    {
        self::asegurarTareaExiste($pdo, $tareaId);
        $datos = self::sincronizarDesdeProgreso($pdo, $progreso);

        $sql = "UPDATE tareas
                SET progreso = :progreso,
                    estado_id = :estado_id,
                    finalizado_en = CASE
                        WHEN :finalizada = 1 THEN COALESCE(finalizado_en, NOW())
                        ELSE NULL
                    END
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":progreso" => $datos["progreso"],
            ":estado_id" => $datos["estado_id"],
            ":finalizada" => $datos["finalizada"] ? 1 : 0,
            ":id" => $tareaId
        ]);

        return $datos;
    }

    public static function actualizarEstado($pdo, $tareaId, $estadoId)
    {
        $tarea = self::obtenerTareaPorId($pdo, $tareaId);
        $datos = self::sincronizarDesdeEstado($pdo, $estadoId, $tarea["progreso"]);

        $sql = "UPDATE tareas
                SET estado_id = :estado_id,
                    progreso = :progreso,
                    finalizado_en = CASE
                        WHEN :finalizada = 1 THEN COALESCE(finalizado_en, NOW())
                        ELSE NULL
                    END
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":estado_id" => $datos["estado_id"],
            ":progreso" => $datos["progreso"],
            ":finalizada" => $datos["finalizada"] ? 1 : 0,
            ":id" => $tareaId
        ]);

        return $datos;
    }

    private static function normalizarProgreso($progreso)
    {
        return max(0, min(100, (int)$progreso));
    }

    private static function obtenerEstadoIdPorNombre($pdo, $nombre)
    {
        $sql = "SELECT id FROM estados_tarea WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":nombre" => $nombre]);
        $estadoId = $stmt->fetchColumn();

        if (!$estadoId) {
            throw new RuntimeException("No se encontró el estado " . $nombre . ".");
        }

        return (int)$estadoId;
    }

    private static function obtenerEstadoPorId($pdo, $estadoId)
    {
        $sql = "SELECT id, nombre FROM estados_tarea WHERE id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $estadoId]);
        $estado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$estado) {
            throw new RuntimeException("Estado no encontrado.");
        }

        return $estado;
    }

    private static function obtenerTareaPorId($pdo, $tareaId)
    {
        $sql = "SELECT id, progreso FROM tareas WHERE id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $tareaId]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tarea) {
            throw new RuntimeException("Tarea no encontrada.");
        }

        return $tarea;
    }

    private static function asegurarTareaExiste($pdo, $tareaId)
    {
        self::obtenerTareaPorId($pdo, $tareaId);
    }
}
