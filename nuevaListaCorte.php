<?php
require("config.php");
require_once("PHPMailer/class.phpmailer.php");
require_once("PHPMailer/class.smtp.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';
$numero = null;
$nro_revision = null;
$modo="create";
if(isset($_GET["modo"])){
  $modo = $_GET["modo"];
}
$advertencia="";
if (!empty($_POST)) {
   //var_dump($_POST);
   //die;
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $fecha      = $_POST['fecha'];
  $idTareaPost = trim((string)($_POST['id_tarea'] ?? ''));
  $id_tarea   = $idTareaPost === '' || $idTareaPost === '__crear__' ? null : filter_var($idTareaPost, FILTER_VALIDATE_INT);
  $nombre     = $_POST['nombre'];
  $adjunto    = $_POST['adjunto'];

  $id_proyecto = $_POST['id_proyecto'];

  //if(empty($advertencia) && $modo == "update" && isset($_GET["id_lista_corte"])) {
  if($modo == "update" && isset($_GET["id_lista_corte"])) {
    $id_lista_corte = $_GET["id_lista_corte"];
    $pdo->beginTransaction();
    try {
      if ($idTareaPost === '__crear__') {
        $estructura = trim((string)($_POST['nueva_tarea_estructura'] ?? ''));
        $idSector = filter_var($_POST['nueva_tarea_sector'] ?? null, FILTER_VALIDATE_INT);
        if ($estructura === '' || mb_strlen($estructura) > 199 || !$idSector) {
          throw new RuntimeException('Para crear la tarea Planos y LC, complete la estructura y el sector.');
        }
        $qTipo = $pdo->prepare("SELECT id FROM tipos_tarea WHERE LOWER(TRIM(tipo)) = 'planos y lc' LIMIT 1");
        $qTipo->execute();
        $idTipoPacking = $qTipo->fetchColumn();
        if (!$idTipoPacking) {
          throw new RuntimeException('No está configurado el tipo de tarea Planos y LC.');
        }
        $qSector = $pdo->prepare('SELECT COUNT(*) FROM sectores WHERE id = ?');
        $qSector->execute([$idSector]);
        if ((int)$qSector->fetchColumn() === 0) {
          throw new RuntimeException('Seleccione un sector válido para la nueva tarea.');
        }
        $qTarea = $pdo->prepare(
          'INSERT INTO tareas (id_proyecto, estructura, id_sector, id_tipo_tarea, id_recurso,
             observaciones, id_coordinador, fecha_inicio_estimada, fecha_fin_estimada,
             fecha_inicio_real, fecha_fin_real, anulado)
           VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0)'
        );
        $qTarea->execute([$id_proyecto, $estructura, $idSector, $idTipoPacking]);
        $id_tarea = (int)$pdo->lastInsertId();
      } else {
        if ($id_tarea === false || $id_tarea === null || $id_tarea < 1) {
          throw new RuntimeException('Seleccione una tarea Planos y LC o elija crear una.');
        }
        $qTarea = $pdo->prepare(
          "SELECT t.id FROM tareas t
           INNER JOIN tipos_tarea tt ON tt.id = t.id_tipo_tarea
           WHERE t.id = ? AND t.id_proyecto = ? AND t.anulado = 0
             AND (LOWER(TRIM(tt.tipo)) = 'planos y lc' OR t.id = (
               SELECT lc_actual.id_tarea FROM listas_corte lc_actual WHERE lc_actual.id = ?
             ))
             AND NOT EXISTS (
               SELECT 1 FROM listas_corte lc_otras
               WHERE lc_otras.id_tarea = t.id AND lc_otras.id <> ?
             )"
        );
        $qTarea->execute([$id_tarea, $id_proyecto, $id_lista_corte, $id_lista_corte]);
        if (!$qTarea->fetchColumn()) {
          throw new RuntimeException('La tarea debe pertenecer al proyecto, ser Planos y LC y no estar vinculada a otra lista de corte.');
        }
      }

      $sql = "UPDATE listas_corte SET fecha=?, id_tarea=?, id_proyecto=?, nombre=?, adjunto=? WHERE id=?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$fecha, $id_tarea, $id_proyecto, $nombre, $adjunto, $id_lista_corte]);
      $pdo->commit();
      $redirect="nuevaListaCorteConjuntos.php?modo=update&id_lista_corte=".$id_lista_corte.$prodParam;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $advertencia = $e->getMessage();
    }
  //}elseif(empty($advertencia)){
  }else{
  
    $id_estado_lista_corte=1;
    $nro_revision=0;
    $anulado=0;
    $descripcion_lc='Emisión original';
    
    $sql = "SELECT count(id) AS cant FROM listas_corte where descripcion = '$descripcion_lc' and id_proyecto = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_proyecto]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $numero_lc = $data['cant']+1;

    $idCuentaRealizo = $_POST['id_cuenta_realizo'] ?? null;
    $idCuentaReviso  = $_POST['id_cuenta_reviso'] ?? null;
    $idCuentaValido  = $_POST['id_cuenta_valido'] ?? null;

    $sql = "SELECT id FROM cuentas WHERE id_usuario = ? ";
    $q = $pdo->prepare($sql);
    $q->execute([$_SESSION['user']['id']]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    if (!empty($data)) {
      $idCuentaRealizo = $data['id'];
    }

    $sql = "INSERT INTO listas_corte (id_proyecto, id_tarea, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, adjunto, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $params = [$id_proyecto, $id_tarea, $fecha, $_SESSION['user']['id'], $id_estado_lista_corte, $nro_revision, $anulado, $nombre, $numero_lc, $adjunto, $descripcion_lc, $idCuentaRealizo, $idCuentaReviso, $idCuentaValido];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $id_lista_corte = (int)$pdo->lastInsertId();

    if($id_lista_corte>0 and $id_tarea>0){
      $sql = "UPDATE tareas SET fecha_inicio_real = IF(fecha_inicio_real IS NULL OR fecha_inicio_real = '0000-00-00', NOW(), fecha_inicio_real) WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_tarea]);
    }
    
    /*$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(),?,'Nueva Lista de Corte','Listas de Corte','imprimirListaCorte.php?id=$id_lista_corte')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));
    
    $descProyecto = getDescripcionProyecto($pdo, $id_proyecto);
    $descripcion_lista_corte = " N° ".$numero_lc. "Rev. N° ".$nro_revision.$descProyecto;

    $idTipoNotificacion=5;
    $idEntidad=$id_lista_corte;
    $detalleNotificacion="ID Lista de Corte: #".$idEntidad;
    $asuntoEmail="Módulo Ingeniería - Nueva Lista de Corte $descripcion_lista_corte";
    $cuerpoEmail="Nueva lista de corte dada de alta en el sistema: $descripcion_lista_corte";
    crearNotificacion($pdo,$idTipoNotificacion,$idEntidad,$detalleNotificacion,$asuntoEmail,$cuerpoEmail);*/
    
    // --- Cargo configuración SMTP desde parámetros ---
    /*$stmt = $pdo->query("SELECT valor FROM parametros WHERE id BETWEEN 1 AND 5 ORDER BY id ASC");
    $smtp = $stmt->fetchAll(PDO::FETCH_COLUMN);// 2. $smtp[0] → id=1, $smtp[1] → id=2, … $smtp[4] → id=5
    list($smtpHost, $smtpUsuario, $smtpClave, $smtpFrom, $smtpFromName) = $smtp;*/
    
    /*$sql = "SELECT t.id_usuario, u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 5 ";
    foreach ($pdo->query($sql) as $row) {
      
      $sql = "INSERT INTO notificaciones (id_tipo_notificacion, id_usuario, fecha_hora, leida, detalle, id_entidad) VALUES (5,?,now(),0,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$row[0],'ID Lista de Corte: #'.$id_lista_corte_revision,$id_lista_corte_revision]);
      
      $address = $row[1];
      
      $titulo = "ERP Notificaciones - Módulo Ingeniería - Nueva Lista de Corte (".$descripcionProyecto.")";
      $mensaje="Nueva lista de corte dada de alta en el sistema: #".$id_lista_corte_revision;
      
      $mail = new PHPMailer();
      $mail->IsSMTP();
      $mail->SMTPAuth = true;
      $mail->Port = 25; 
      //$mail->SMTPSecure = 'ssl';
      //$mail->SMTPAutoTLS = false;
      $mail->SMTPSecure = false;
      $mail->IsHTML(true); 
      $mail->CharSet = "utf-8";
      $mail->From = $smtpFrom;
      $mail->FromName = $_SESSION['user']['usuario'];
      $mail->Host = $smtpHost; 
      $mail->Username = $smtpUsuario; 
      $mail->Password = $smtpClave;
      $mail->AddAddress($address);
      $mail->Subject = $titulo; 
      $mensajeHtml = nl2br($mensaje);
      $mail->Body = "{$mensajeHtml} <br /><br />"; 
      $mail->AltBody = "{$mensaje} \n\n"; 
      $mail->Send();
    
    }*/

    // 1) Recuperar todos los usuarios de golpe
    /*$sql = "SELECT t.id_usuario, u.email FROM usuarios_tipos_notificacion t INNER JOIN usuarios u ON u.id = t.id_usuario WHERE t.id_tipo_notificacion = 5";
    $users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // 2) Batch-insert de notificaciones
    if (!empty($users)) {
      $values    = [];
      $params    = [];
      $detalle   = 'ID Lista de Corte: #'.$id_lista_corte;
      foreach ($users as $u) {
        $values[] = "(5, ?, NOW(), 0, ?, ?)";
        $params[] = $u['id_usuario'];
        $params[] = $detalle;
        $params[] = $id_lista_corte;
      }
      $insertSql = "INSERT INTO notificaciones (id_tipo_notificacion, id_usuario, fecha_hora, leida, detalle, id_entidad) VALUES ".implode(', ', $values);
      $pdo->prepare($insertSql)->execute($params);
    }

    // 3) Preparar y enviar un único e-mail

    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->SMTPAuth      = true;
    $mail->SMTPKeepAlive = true;    // Mantiene la conexión viva para no reconectar
    $mail->Host          = $smtpHost;
    $mail->Port          = 25;
    $mail->SMTPSecure    = false;
    $mail->Username      = $smtpUsuario;
    $mail->Password      = $smtpClave;

    $mail->setFrom($smtpFrom, $_SESSION['user']['usuario']);
    $mail->isHTML(true);
    $mail->CharSet       = 'utf-8';
    $mail->Subject       = "ERP Notificaciones - Módulo Ingeniería - Nueva Lista de Corte ({$descripcionProyecto})";

    $plainMsg  = "Nueva lista de corte dada de alta en el sistema: #{$id_lista_corte}";
    $htmlMsg   = nl2br($plainMsg) . '<br><br>';
    $mail->Body    = $htmlMsg;
    $mail->AltBody = $plainMsg;

    // Agregar todos como BCC (no ven entre sí las direcciones)
    foreach ($users as $u) {
      $mail->addBCC($u['email']);
    }

    // Enviar todo en un solo envío SMTP
    if (!$mail->send()) {
      error_log('Error enviando notificación masiva: ' . $mail->ErrorInfo);
      // manejar el error según tu lógica
    }*/

    $redirect="nuevaListaCorteConjuntos.php?id_lista_corte=".$id_lista_corte.$prodParam;
  }

  if ($advertencia === '') {
    Database::disconnect();
    header("Location: $redirect");
    exit();
  }
  Database::disconnect();
}
  $fecha = FECHA_HOY;
  $id_tarea="";
  if (!empty($_GET['idTarea'])) {
    $id_tarea = $_GET['idTarea']; 
  }

  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $id_proyecto="";
  if (!empty($id_tarea)) {
    $sql = "SELECT id_proyecto from tareas where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_tarea]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_proyecto = $data['id_proyecto'];
  }

  if(isset($_GET['idProyecto'])){
    $id_proyecto = $_GET['idProyecto'];
  }

  $nombre="";
  $adjunto="";
  $tareasPackingList = [];
  $sectoresTarea = [];
  $titulo="Nueva";
  $accion="Crear";
  $formAction="nuevaListaCorte.php$prodQuery";
  if($modo == 'update' && isset($_GET['id_lista_corte'])) {
    $titulo="Modificar";
    $accion=$titulo;
    $id_lista_corte = $_GET['id_lista_corte'];
    $formAction .= ($prodQuery ? '&' : '?')."modo=update&id_lista_corte=".$id_lista_corte;

    $sql = "SELECT fecha, id_tarea, id_proyecto, nombre, adjunto, numero, nro_revision, id_estado_lista_corte FROM listas_corte WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    if ($data) {
      $fecha=$data["fecha"];
      $id_tarea=$data["id_tarea"];
      $id_proyecto=$data["id_proyecto"];
      $nombre=$data["nombre"];
      $adjunto=$data["adjunto"];
      $numero=$data["numero"];
      $nro_revision=$data["nro_revision"];
      $id_estado_lista_corte = $data["id_estado_lista_corte"];
    }

    $qTareasPacking = $pdo->prepare(
      "SELECT t.id, t.estructura, tt.tipo
       FROM tareas t INNER JOIN tipos_tarea tt ON tt.id = t.id_tipo_tarea
       WHERE t.id_proyecto = ? AND t.anulado = 0
         AND (LOWER(TRIM(tt.tipo)) = 'planos y lc' OR t.id = ?)
         AND NOT EXISTS (
           SELECT 1 FROM listas_corte lc2
           WHERE lc2.id_tarea = t.id AND lc2.id <> ?
         )
       ORDER BY t.estructura"
    );
    $qTareasPacking->execute([$id_proyecto, $id_tarea ?: 0, $id_lista_corte]);
    $tareasPackingList = $qTareasPacking->fetchAll(PDO::FETCH_ASSOC);
    $sectoresTarea = $pdo->query('SELECT id, sector FROM sectores ORDER BY sector')->fetchAll(PDO::FETCH_ASSOC);
  }

  // Verifico existencia de cómputos asociados al proyecto
  $sql = "SELECT COUNT(*) FROM computos co INNER JOIN tareas t ON t.id = co.id_tarea WHERE t.id_proyecto = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_proyecto]);
  $tieneComputos = $q->fetchColumn();
  //die();

  // Verifico existencia de pedidos asociados al proyecto
  $sql = "SELECT COUNT(*) FROM pedidos WHERE id_proyecto = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_proyecto]);
  $tienePedidos = $q->fetchColumn();

  if($tieneComputos==0 && $tienePedidos==0){
    $advertencia='El proyecto seleccionado debe contar con cómputos y pedidos de materiales antes de continuar.';
  }

  /*$pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from tareas t inner join proyectos p on p.id = t.id_proyecto inner join sitios s on s.id = p.id_sitio where t.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $descProyecto = $data['nro_sitio'].'-'.$data['nro_subsitio'].'-'.$data['nro'].': '.$data['nombre'];
  
  $sql2 = "SELECT id from cuentas where id_usuario = ? ";
  $q2 = $pdo->prepare($sql2);
  $q2->execute([$_SESSION['user']['id']]);
  
  $data2 = $q2->fetch(PDO::FETCH_ASSOC);
  
  $idCuenta = 0;
  if (!empty($data2['id'])) {
    $idCuenta = $data2['id'];	
  }*/
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion=$titulo." Lista de Corte";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?php if($numero !== null && $nro_revision !== null){ echo $titulo." Lista de Corte Nº {$numero} - Rev {$nro_revision}"; } else { echo $ubicacion; } ?></h5>
                  </div>
                                          <form class="form theme-form" role="form" method="post" action="<?=$formAction?>" enctype="multipart/form-data">
                    <input type="hidden" name="prod" value="<?= $_REQUEST['prod'] ?? '' ?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha(*)</label>
                            <div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=$fecha?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nombre de la LC(*)</label>
                            <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" autofocus class="form-control" required="required" value="<?=$nombre?>"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Proyecto(*)</label>
                            <div class="col-sm-9">
                              <select name="id_proyecto" id="id_proyecto" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
								
                                $sqlZon = "SELECT p.id, s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  $selected="";
                                  if ($id_proyecto == $fila['id']) {
                                    $selected=" selected ";
                                  }?>
                                  <option value='<?=$fila['id']?>' <?=$selected?>><?=$fila['nro_sitio'].'-'.$fila['nro_subsitio'].'-'.$fila['nro'].': '.$fila['nombre']?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div><?php
                          if(!empty($advertencia)){?>
                            <div class="alert alert-warning mb-0" role="alert"><?=$advertencia?></div><?php
                          }?>
                          <!-- <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Revisó(*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_reviso" id="id_cuenta_reviso" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE (id_tipo_cuenta in (4) or id in (select id_cuenta from cuentas_x_tipos_cuenta where id_tipo_cuenta in (4))) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Aprobó(*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_valido" id="id_cuenta_valido" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE (id_tipo_cuenta in (4) or id in (select id_cuenta from cuentas_x_tipos_cuenta where id_tipo_cuenta in (4))) and activo = 1 and anulado = 0";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['nombre']."</option>";
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div> -->
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Adjuntar Plano</label>
                            <div class="col-sm-9"><input name="adjunto" type="text" value="<?=$adjunto?>" class="form-control"></div>
                            <input type="hidden" name="hId" value="1" />
                          </div>
                          <?php if ($modo === 'update') { ?>
                            <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Tarea Planos y LC(*)</label>
                              <div class="col-sm-9">
                                <select name="id_tarea" id="id_tarea" class="form-control" required>
                                  <option value="">Seleccione una tarea...</option>
                                  <?php foreach ($tareasPackingList as $tarea): ?>
                                    <option value="<?=$tarea['id']?>" <?=((string)$id_tarea === (string)$tarea['id']) ? 'selected' : ''?>>
                                      #<?=$tarea['id']?> — <?=htmlspecialchars($tarea['estructura'], ENT_QUOTES, 'UTF-8')?> (<?=htmlspecialchars($tarea['tipo'], ENT_QUOTES, 'UTF-8')?>)
                                    </option>
                                  <?php endforeach; ?>
                                  <option value="__crear__">Crear nueva tarea Planos y LC…</option>
                                </select>
                              </div>
                            </div>
                            <div class="form-group row d-none" id="nuevaTareaPackingFields">
                              <label class="col-sm-3 col-form-label">Nueva tarea</label>
                              <div class="col-sm-9">
                                <input type="text" name="nueva_tarea_estructura" maxlength="199" class="form-control mb-2" placeholder="Estructura / nombre">
                                <select name="nueva_tarea_sector" class="form-control">
                                  <option value="">Seleccione sector...</option>
                                  <?php foreach ($sectoresTarea as $sector): ?>
                                    <option value="<?=$sector['id']?>"><?=htmlspecialchars($sector['sector'], ENT_QUOTES, 'UTF-8')?></option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                            </div>
                          <?php } else { ?>
                            <input type="hidden" name="id_tarea" value="<?=htmlspecialchars((string)$id_tarea, ENT_QUOTES, 'UTF-8')?>" />
                          <?php } ?>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" type="submit"><?=$accion?> y agregar Conjuntos</button>
                        <?php if ($modo == 'update' && $id_estado_lista_corte == 1) { ?>
                        <button class="btn btn-primary" type="button" id="btnEnviarAprobacion">Enviar a aprobación</button>
                        <?php } ?>
                        <a href="listarListasCorte.php<?= $prodQuery ?>" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <div class="modal fade" id="modalEnviarAprobacion" tabindex="-1" role="dialog">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <form id="formEnviarAprobacion" method="post" action="enviarAprobacionListaCorte.php?id_lista_corte=<?=$id_lista_corte ?><?= $prodParam ?>">
                <input type="hidden" name="prod" value="<?= $_REQUEST['prod'] ?? '' ?>">
                <div class="modal-header">
                  <h5 class="modal-title">Confirmar envío a aprobación</h5>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                  <p>¿Estás seguro de que quieres enviar esta revisión a aprobación?</p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-primary">Confirmar</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>

    <!-- latest jquery-->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap js-->
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <!-- feather icon js-->
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <!-- Sidebar jquery-->
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <!-- Plugins JS start-->
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
	  <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
	  <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.buttons.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/jszip.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.colVis.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/pdfmake.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/vfs_fonts.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.autoFill.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.select.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.html5.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.print.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.responsive.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/responsive.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.keyTable.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.colReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.fixedHeader.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.rowReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.scroller.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <script>
      $(document).ready(function() {
        function actualizarCamposNuevaTarea() {
          var crear = $('#id_tarea').val() === '__crear__';
          $('#nuevaTareaPackingFields').toggleClass('d-none', !crear);
          $('#nuevaTareaPackingFields input, #nuevaTareaPackingFields select').prop('required', crear);
        }
        $('#id_tarea').on('change', actualizarCamposNuevaTarea);
        actualizarCamposNuevaTarea();
        $('#btnEnviarAprobacion').on('click', function(){
          $('#modalEnviarAprobacion').modal('show');
        });
      });
    </script>
  </body>
</html>
