<?php
$modoDebug=0;
if($modoDebug == 1) {
  $_SERVER["REQUEST_METHOD"] = "POST";
  $_POST["ajax"] = true; // Simulamos una petición AJAX para pruebas
  $_POST["accion"] = "aprobar"; // Acción de prueba
  $_POST["id_lista_corte"] = 98; // ID de cómputo de prueba
  $_SESSION['user']['id'] = 1; // Simulamos un usuario logueado
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"])) {
  include_once 'config.php';
  include_once 'database.php';
  $pdo = Database::connect();

  $accion = $_POST["accion"];

  try {
    if (!isset($_POST["id_lista_corte"])) {
      throw new Exception("ID de lista de corte no recibido.");
    }

    $pdo->beginTransaction();

    $id_lista_corte = (int)$_POST["id_lista_corte"];

    $aprobando=0;
    switch ($accion) {
      case "aprobar":
        $aprobando=1;
        $detalle_accion = "Aprobación de la lista de corte";

        // Aprobar el cómputo
        $sql = "UPDATE listas_corte SET id_estado_lista_corte = 3 WHERE id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id_lista_corte]);

        if ($modoDebug == 1) {
          echo debugQuery($pdo, $sql, [$id_lista_corte]);
          echo "<br><br>Afe: " . $q->rowCount();
        }

        break;

      default:
        http_response_code(400);
        echo "Acción no válida";
        exit;
    }

    $textoComputo = "";

    if($aprobando==1){

      // 3) Superar la revisión anterior y acumular su texto
      //$textoComputo .= superarRevisionAnterior($pdo, $id_lista_corte, $modoDebug);

    }

    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, '$detalle_accion.$textoComputo', 'Listas de Corte', 'imprimirListaCorte.php?id={$id_lista_corte}')";
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