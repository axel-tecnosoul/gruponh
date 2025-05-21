<?php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"])) {
    include_once 'config.php';
    include_once 'database.php';
    $pdo = Database::connect();

    $accion = $_POST["accion"];

    try {
        switch ($accion) {
            case "aprobar_completo":
                if (!isset($_POST["ids_computo"]) || !is_array($_POST["ids_computo"])) {
                    throw new Exception("Faltan IDs de cómputos.");
                }

                foreach ($_POST["ids_computo"] as $id) {
                    $id = (int)$id;
                    $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id]); // 3 = aprobado
                }
                break;

            case "aprobar_parcial":
                if (!isset($_POST["ids_computo"]) || !is_array($_POST["ids_computo"])) {
                    throw new Exception("Faltan IDs de cómputos.");
                }

                foreach ($_POST["ids_computo"] as $id) {
                    $id = (int)$id;

                    // Evita aprobar los que ya están cancelados (estado 6)
                    $stmt = $pdo->prepare("SELECT id_estado FROM computos WHERE id = ?");
                    $stmt->execute([$id]);
                    $estado = $stmt->fetchColumn();

                    if ($estado != 6) {
                        $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id]);
                    }
                }
                break;

            case "cancelar_computo":
                if (!isset($_POST["id_computo"])) {
                    throw new Exception("ID de cómputo no recibido.");
                }
                $id = (int)$_POST["id_computo"];
                $pdo->prepare("UPDATE computos SET id_estado = 6 WHERE id = ?")->execute([$id]); // 6 = cancelado
                break;

            // Si la acción no es válida (no coincide con las opciones predefinidas),
            // se responde con un código de error 400 (Bad Request) y un mensaje indicando que la acción no es válida.
            default:
                http_response_code(400); // Establece el código de respuesta HTTP 400 (Bad Request).
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
