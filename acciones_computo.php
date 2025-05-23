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

    $pdo->beginTransaction();

    $modoDebug=0;

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
      $sql = "SELECT c.nro_revision, c.nro AS nro_computo,c.id_tarea FROM computos c WHERE c.id = $id_computo";
      $q = $pdo->prepare($sql);
      $q->execute();
      $data = $q->fetch(PDO::FETCH_ASSOC);

      if ($modoDebug == 1) {
        echo $sql."<br>";
        var_dump($data);
      }

      $nro_revision=$data['nro_revision'];

      if($nro_revision>0){
        
        $revision_anterior=$nro_revision-1;

        $sql = "UPDATE computos SET id_estado = 7 WHERE id_tarea = ? AND nro = ? AND nro_revision = ?";
        $q = $pdo->prepare($sql);
        $params = [$data['id_tarea'],$data['nro_computo'],$revision_anterior];
        $q->execute($params);
        $textoComputo.=". Revisión anterior N° ".$revision_anterior." superada.";

        if ($modoDebug == 1) {
          // Generar y mostrar la consulta “real”
          $fullSql = debugQuery($pdo, $sql, $params);
          echo $fullSql . "<br><br>";
        }
      }
    }

    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, '$detalle_accion.$textoComputo', 'Cómputos', 'verComputo.php?id={$id_computo}')";
    $q = $pdo->prepare($sql);
    $q->execute([ $_SESSION['user']['id'] ]);

    //$pdo->rollBack();
    $pdo->commit();

    echo "ok";
  } catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
  }

    Database::disconnect();
    exit;
}
