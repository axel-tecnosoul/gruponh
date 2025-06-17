<?php
$modoDebug=0;
if($modoDebug == 1) {
  $_SERVER["REQUEST_METHOD"] = "POST";
  $_POST["ajax"] = true; // Simulamos una petición AJAX para pruebas
  $_POST["accion"] = "aprobar_completo"; // Acción de prueba
  $_POST["id_computo"] = 100; // ID de cómputo de prueba
  $_SESSION['user']['id'] = 1; // Simulamos un usuario logueado
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"])) {
  include_once 'config.php';
  include_once 'database.php';
  $pdo = Database::connect();

  $accion = $_POST["accion"];

  try {
    if (!isset($_POST["id_computo"])) {
      throw new Exception("ID de cómputo no recibido.");
    }

    $pdo->beginTransaction();

    $id_computo = (int)$_POST["id_computo"];

    $aprobando=0;
    switch ($accion) {
      case "aprobar_completo":
        $aprobando=1;
        $detalle_accion = "Aprobación del cómputo y todos sus conceptos";

        // Aprobar el cómputo
        $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id_computo]);

        // Aprobar todos los conceptos relacionados con el cómputo (independientemente del estado cancelado)
        $pdo->prepare("UPDATE computos_detalle SET aprobado = 1, cancelado = 0 WHERE id_computo = ?")->execute([$id_computo]);
        break;

      case "aprobar_parcial":
        $aprobando=1;
        $detalle_accion = "Aprobación del cómputo y los conceptos que no están cancelados";

        // Aprobar los conceptos que NO están cancelados (cancelado = 0)
        $pdo->prepare("UPDATE computos_detalle SET aprobado = 1 WHERE id_computo = ? AND cancelado = 0")->execute([$id_computo]);

        // Cambiar el estado del cómputo a aprobado
        $pdo->prepare("UPDATE computos SET id_estado = 3 WHERE id = ?")->execute([$id_computo]);
        break;

      case "cancelar_computo":
        $detalle_accion = "Cancelación del cómputo y todos sus conceptos";

        // Cambiar el estado del cómputo a cancelado
        $pdo->prepare("UPDATE computos SET id_estado = 6 WHERE id = ?")->execute([$id_computo]);

        // Cancelar todos los conceptos asociados a ese cómputo
        $pdo->prepare("UPDATE computos_detalle SET cancelado = 1, aprobado = 0 WHERE id_computo = ?")->execute([$id_computo]);
        break;

      default:
        http_response_code(400);
        echo "Acción no válida";
        exit;
    }

    $textoComputo = "";

    if($aprobando==1){

      // 3) Superar la revisión anterior y acumular su texto
      $textoComputo .= superarRevisionAnterior($pdo, $id_computo, $modoDebug);

    }

    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, '$detalle_accion.$textoComputo', 'Cómputos', 'verComputo.php?id={$id_computo}')";
    $q = $pdo->prepare($sql);
    $q->execute([ $_SESSION['user']['id'] ]);

    if ($modoDebug == 1) {
      $pdo->rollBack();
    }else{
      $pdo->commit();
    }

    echo "ok";
  } catch (Exception $e) {

    // Si hubo un beginTransaction() activo, lo revertimos
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }

    http_response_code(500);
    echo "Error: " . $e->getMessage();
  }finally {
    // Siempre cerramos la conexión
    Database::disconnect();
    exit;
  }

}
