<?php
//require("config.php");
/*if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}*/
/*require 'database.php';

$id = null;
if (!empty($_REQUEST['id_computo_detalle'])) {
  $id = $_REQUEST['id_computo_detalle'];
}

$idComputo=$_REQUEST['id_computo'];

if (null==$id) {
  header("Location: listarComputos.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "UPDATE computos_detalle SET aprobado = 1 WHERE id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id]);

$sql = "SELECT count(*) cant FROM computos_detalle WHERE aprobado = 0 and id_computo = ? ";
$q = $pdo->prepare($sql);
$q->execute([$idComputo]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if ($data['cant'] == 0) {
  $sql = "UPDATE computos SET id_estado = 3 WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$idComputo]);
}

$sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Aprobación de detalle de cómputo','Cómputos','verComputo.php?id=$id')";
$q = $pdo->prepare($sql);
$q->execute(array($_SESSION['user']['id']));

Database::disconnect();*/
    
//header("Location: verComputo.php?id=".$idComputo);
//header("Location: listarComputos.php?id=".$idComputo);


require("config.php");
require 'database.php';

// Siempre devolvemos JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Leemos los parámetros
    $idDetalle = $_REQUEST['id_computo_detalle'] ?? null;
    $idComputo = $_REQUEST['id_computo']           ?? null;

    if (empty($idDetalle) || empty($idComputo)) {
        throw new Exception("Parámetros insuficientes.");
    }

    // Conexión
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();
    $modoDebug=0;

    // 1) Marcar detalle como aprobado
    $sql = "UPDATE computos_detalle SET aprobado = 1 WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$idDetalle]);

    // 2) Verificar si quedan pendientes
    //$sql = "SELECT COUNT(*) AS cant FROM computos_detalle WHERE aprobado = 0 AND cancelado = 0 AND id_computo = ?";
    $sql = "SELECT COUNT(*) AS cant,c.nro_revision,c.nro AS nro_computo,c.id_tarea FROM computos_detalle cd JOIN computos c ON cd.id_computo=c.id WHERE cd.aprobado = 0 AND cd.cancelado = 0 AND cd.id_computo = $idComputo";
    if ($modoDebug == 1) {
      echo $sql."<br>";
    }
    $q = $pdo->prepare($sql);
    $q->execute();
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($modoDebug == 1) {
      var_dump($data);
    }

    $textoComputo = "";
    if ($data['cant'] == 0) {

      $nro_revision=$data['nro_revision'];

      // Si no quedan pendientes, cambiar estado del cómputo
      $sql = "UPDATE computos SET id_estado = 3 WHERE id = ?";
      $q = $pdo->prepare($sql);
      $params = [$idComputo];
      $q->execute($params);
      $textoComputo = " Computo aprobado en su totalidad. (actualice la pagina para ver el nuevo estado)";
        
      if ($modoDebug == 1) {
        // Generar y mostrar la consulta “real”
        $fullSql = debugQuery($pdo, $sql, $params);
        echo $fullSql . "<br><br>";
        echo $nro_revision."<br>";
      }

      if($nro_revision>0){
        
        $revision_anterior=$nro_revision-1;

        $sql = "UPDATE computos SET id_estado = 7 WHERE id_tarea = ? AND nro = ? AND nro_revision = ?";
        $q = $pdo->prepare($sql);
        $params = [$data['id_tarea'],$data['nro_computo'],$revision_anterior];
        $q->execute($params);
        $textoComputo.= " Revisión anterior N° ".$revision_anterior." superada.";

        
        if ($modoDebug == 1) {
          // Generar y mostrar la consulta “real”
          $fullSql = debugQuery($pdo, $sql, $params);
          echo $fullSql . "<br><br>";
        }
      }

    }

    // 3) Insertar log
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Aprobación de detalle de cómputo', 'Cómputos', 'verComputo.php?id={$idComputo}')";
    $q = $pdo->prepare($sql);
    $q->execute([ $_SESSION['user']['id'] ]);


    //$pdo->rollBack();
    $pdo->commit();

    // Desconectar
    Database::disconnect();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Concepto aprobado correctamente.'.$textoComputo
    ]);

} catch (Exception $e) {
    // En caso de error devolvemos error y mensaje
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
