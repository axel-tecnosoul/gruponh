<?php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"])) {
    include_once 'config.php';
    include_once 'database.php';
    $pdo = Database::connect();

    $accion = $_POST["accion"];

    try {
        if (!isset($_POST["id_computo"])) {
            throw new Exception("ID de cómputo no recibido.");
        }

        $id = (int)$_POST["id_computo"];

        switch ($accion) {
            case "aprobar_completo":
                // Aprobar el cómputo
                $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id]);

                // Aprobar todos los conceptos relacionados con el cómputo (independientemente del estado cancelado)
                $pdo->prepare("UPDATE computos_detalle SET aprobado = 1, cancelado = 0 WHERE id_computo = ?")->execute([$id]);
                break;

            case "aprobar_parcial":
                // Aprobar los conceptos que NO están cancelados (cancelado = 0)
                $pdo->prepare("UPDATE computos_detalle SET aprobado = 1 WHERE id_computo = ? AND cancelado = 0")->execute([$id]);

                // Cambiar el estado del cómputo a aprobado (solo si no está cancelado)
                /*$stmt = $pdo->prepare("SELECT id_estado FROM computos WHERE id = ?");
                $stmt->execute([$id]);
                $estado = $stmt->fetchColumn();

                if ($estado != 6) { // Si el cómputo no está cancelado
                    $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id]);
                }*/
                
                // Cambiar el estado del cómputo a aprobado
                $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id]);
                break;

            case "cancelar_computo":
                // Cambiar el estado del cómputo a cancelado
                $pdo->prepare("UPDATE computos SET id_estado = 6 WHERE id = ?")->execute([$id]);

                // Cancelar todos los conceptos asociados a ese cómputo
                $pdo->prepare("UPDATE computos_detalle SET cancelado = 1, aprobado = 0 WHERE id_computo = ?")->execute([$id]);
                break;

            default:
                http_response_code(400);
                echo "Acción no válida";
                exit;
        }

        echo "ok";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }

    Database::disconnect();
    exit;
}
