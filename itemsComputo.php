<?php
require("config.php");
require 'database.php';
require("PHPMailer/class.phpmailer.php");
require("PHPMailer/class.smtp.php");

// 1) Control de acceso
/*session_start();
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}*/

// 2) Recoger parámetros comunes
$modo      = $_REQUEST['modo']      ?? 'nuevo';     // 'nuevo' o 'update'
$idOrigen  = $_REQUEST['id']        ?? null;         // id del cómputo original
$nroRev    = $_REQUEST['revision']  ?? 0;            // número de revisión actual

if (!$idOrigen) {
  header("Location: listarComputos.php");
  exit;
}

$id      = $idOrigen;
$revision = $nroRev;

// 3) Conexión PDO
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 4) Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Variables de sesión
  $userId = $_SESSION['user']['id'];

  // Si viene confirmación de enviar a aprobación:
  if (isset($_POST['btn2_confirm'])) {
    // Parámetros
    $idComp   = $_POST['id'];
    $revision = $_POST['revision'];

    // 1) Actualizar el estado de LA REVISIÓN ACTUAL a "Para aprobar" (id_estado = 2)
    $stmt = $pdo->prepare("UPDATE computos SET id_estado = 2 WHERE id = ?");
    $stmt->execute([$idComp]);

    // 2) Marcar la revisión ANTERIOR como “Superada” (id_estado = 7)
    /*$stmt = $pdo->prepare("
      UPDATE computos
      SET id_estado = 7
      WHERE nro_computo = (
        SELECT nro_computo FROM computos WHERE id = ?
      )
        AND id <> ?
        AND id_estado = 2
    ");
    $stmt->execute([$idComp, $idComp]);*/

    // 3) Crear notificaciones y envío de email (idéntico al original)
    // --- Cargo configuración SMTP desde parámetros ---
    $smtp = [];
    for ($i = 1; $i <= 5; $i++) {
      $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE id = ?");
      $stmt->execute([$i]);
      $smtp[$i] = $stmt->fetchColumn();
    }
    list($smtpHost, $smtpUsuario, $smtpClave, $smtpFrom, $smtpFromName) = [$smtp[1], $smtp[2], $smtp[3], $smtp[4], $smtp[5]];

    // --- Descripción del proyecto ---
    $stmt = $pdo->prepare("SELECT s.nro_sitio, s.nro_subsitio, p.nro, p.nombre FROM computos c JOIN tareas t ON t.id = c.id_tarea JOIN proyectos p ON p.id = t.id_proyecto JOIN sitios s ON s.id = p.id_sitio WHERE c.id = ?");
    $stmt->execute([$idComp]);
    $fila = $stmt->fetch(PDO::FETCH_NUM);
    $descProyecto = "{$fila[0]} - {$fila[1]} - {$fila[2]} - {$fila[3]}";

    $whereDebug=" AND u.id = 1";//QUITAR -> SOLO PARA DESARROLLO

    // --- Recorro usuarios suscriptos al tipo_notificación = 8 ---
    $sql = "SELECT t.id_usuario, u.email FROM usuarios_tipos_notificacion t JOIN usuarios u ON u.id = t.id_usuario WHERE t.id_tipo_notificacion = 8".$whereDebug;
    foreach ($pdo->query($sql) as $row) {
      list($destUsuario, $destEmail) = $row;
      // Inserto en notificaciones
      $stmt = $pdo->prepare("INSERT INTO notificaciones (id_tipo_notificacion, id_usuario, fecha_hora, leida, detalle, id_entidad) VALUES (8, ?, NOW(), 0, ?, ?)");
      $detalle = "ID Cómputo: #{$idComp}";
      $stmt->execute([$destUsuario, $detalle, $idComp]);

      // Armo y envío mail
      $titulo  = "ERP Notificaciones - Producción - Revisión Cómputo ({$descProyecto})";
      $mensaje = "La revisión de cómputo #{$descProyecto} está lista para aprobación.";

      $mail = new PHPMailer();
      $mail->IsSMTP();
      $mail->Host       = $smtpHost;
      $mail->SMTPAuth   = true;
      $mail->Username   = $smtpUsuario;
      $mail->Password   = $smtpClave;
      $mail->Port       = 25;
      $mail->SMTPSecure = false;
      $mail->From       = $smtpFrom;
      $mail->FromName   = $_SESSION['user']['usuario'];
      $mail->CharSet    = "utf-8";
      $mail->IsHTML(true);
      $mail->AddAddress($destEmail);
      $mail->Subject    = $titulo;
      $mail->Body       = nl2br($mensaje) . "<br><br>";
      $mail->AltBody    = $mensaje;
      $mail->Send();
    }

    // 4) Redirijo al listado
    header("Location: listarComputos.php");
    exit;
  }

  // ¿Es inicio de una revisión nueva?
  $esRevision = ($modo === 'update' && !empty($_POST['motivoRevision']));

  if ($esRevision) {
    // --- Inicio transacción para nueva revisión ---
    $pdo->beginTransaction();
    try {
      // 4.1) Marcar cómputo original como "Superada" (id_estado = 7)
      /*$stmt = $pdo->prepare("UPDATE computos SET id_estado = 7 WHERE id = ?");
      $stmt->execute([$idOrigen]);*/

      // 4.2) Cargar datos del cómputo original
      $stmt = $pdo->prepare("SELECT nro_revision, id_tarea, fecha, id_cuenta_solicitante, nro, nro_computo FROM computos WHERE id = ?");
      $stmt->execute([$idOrigen]);
      $orig = $stmt->fetch(PDO::FETCH_ASSOC);

      // 4.3) Determinar nuevos valores
      $nuevoNroRev      = $orig['nro_revision'] + 1;
      $motivo           = trim($_POST['motivoRevision']);
      // Obtener la cuenta del revisor (basada en user logueado)
      $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE id_usuario = ?");
      $stmt->execute([$userId]);
      $rev = $stmt->fetchColumn();
      // Para validación, puedes usar el mismo usuario o buscar otra lógica
      $val = $rev;

      // 4.4) Insertar nuevo registro en computos
      $stmt = $pdo->prepare("INSERT INTO computos (nro_revision, id_tarea, fecha, id_cuenta_solicitante, id_estado, nro_computo, comentarios_revision, fecha_hora_revision, nro, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido) VALUES (?, ?, ?, ?, 1, ?, ?, NOW(), ?, ?, ?, ?)");
      $stmt->execute([
        $nuevoNroRev,
        $orig['id_tarea'],
        $orig['fecha'],
        $orig['id_cuenta_solicitante'],
        $orig['nro_computo'],
        $motivo,
        $orig['nro'],
        $rev,       // quien realiza
        $rev,       // quien revisa
        $val        // quien valida
      ]);
      $idRevision = $pdo->lastInsertId();

      // 4.5) Duplicar todos los detalles del cómputo original
      /*$stmt = $pdo->prepare("INSERT INTO computos_detalle (id_computo, id_material, cantidad, fecha_necesidad, aprobado, reservado, comprado, cancelado, comentarios) SELECT ?, id_material, cantidad, fecha_necesidad, aprobado, reservado, comprado, cancelado, comentarios FROM computos_detalle WHERE id_computo = ?");
      $stmt->execute([$idRevision, $idOrigen]);*/

      // 4.5) Duplicar todos los detalles del cómputo original pero forzar aprobado, reservado, comprado y cancelado a 0
      $stmt = $pdo->prepare("INSERT INTO computos_detalle (id_computo, id_material, cantidad, fecha_necesidad, aprobado, reservado, comprado, cancelado, comentarios) SELECT ?, id_material, cantidad, fecha_necesidad, 0, 0, 0, 0, comentarios FROM computos_detalle WHERE id_computo = ?");
      $stmt->execute([$idRevision, $idOrigen]);

      $pdo->commit();
    } catch (Exception $e) {
      $pdo->rollBack();
      die("Error al generar revisión: " . $e->getMessage());
    }

    // Reasignamos las variables para que el form siga apuntando a la nueva revisión
    $id       = $idRevision;
    $nroRev   = $nuevoNroRev;

  } else {
    // No es nueva revisión: seguimos trabajando sobre el mismo $idOrigen
    $id = $idOrigen;
  }

  // 5) Ahora procesamos el envío de un nuevo detalle (común a ambos modos)
  //    (Aquí mantenemos la lógica existente de INSERT o UPDATE de ítems)
  if (!empty($_POST['id_material'])) {
    // Evito duplicados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM computos_detalle WHERE cancelado = 0 AND id_computo = ? AND id_material = ?");
    $stmt->execute([$id, $_POST['id_material']]);
    if ($stmt->fetchColumn() > 0) {
      header("Location: itemsComputo.php?modo=$modo&id=$id&revision=$nroRev&error=1");
      exit;
    }

    // Inserto el nuevo ítem
    $stmt = $pdo->prepare("INSERT INTO computos_detalle (id_computo, id_material, cantidad, fecha_necesidad, aprobado, comentarios) VALUES (?, ?, ?, ?, 0, ?)");
    $stmt->execute([
      $id,
      $_POST['id_material'],
      $_POST['cantidad'],
      $_POST['fecha_necesidad'],
      $_POST['comentarios']
    ]);

    // Logueo la acción
    $stmt = $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Se ha modificado un item de un cómputo', 'Cómputos', ?)");
    $link = "verComputo.php?id=$id";
    $stmt->execute([$userId, $link]);
  }

  // 6) Redirección final
  if (isset($_POST['btn2'])) {
    // Solo "Enviar a aprobación" (cambia de nombre en el HTML)
    header("Location: listarComputos.php");
  } else {
    // "Crear y agregar otro"
    header("Location: itemsComputo.php?modo=$modo&id=$id&revision=$nroRev");
  }
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT s.nro_sitio AS sitio, s.nro_subsitio AS subsitio, p.nro AS nro_proyecto, p.nombre AS proyecto, c.nro AS nro_computo, nro_revision FROM computos c LEFT JOIN tareas t ON c.id_tarea=t.id LEFT JOIN tipos_tarea tt on tt.id = t.id_tipo_tarea LEFT JOIN cuentas cu ON cu.id = c.id_cuenta_solicitante LEFT JOIN estados_computos ec ON ec.id = c.id_estado INNER JOIN proyectos p on p.id = t.id_proyecto INNER JOIN sitios s on s.id = p.id_sitio WHERE c.id = ? ";

$q = $pdo->prepare($sql);
$q->execute([$id]);
$data = $q->fetch(PDO::FETCH_ASSOC);

Database::disconnect();
// 7) Resto del HTML (tu form, tabla, etc.) se mantiene igual,
//    y quitar los campos comentados de observaciones / cuentas.
?>
<!DOCTYPE html>
<html lang="en">
  <head><?php
    include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("miFormulario");
        const btn1 = document.getElementById("btn1");
        const btn2 = document.getElementById("btn2");
        const observaciones = document.getElementById("observaciones");
        const id_cuenta_valido = document.getElementById("id_cuenta_valido");
        const id_cuenta_reviso = document.getElementById("id_cuenta_reviso");

        // Al presionar el botón, ajustamos el atributo "required" dinámicamente
        btn1.addEventListener("click", function () {
          observaciones.removeAttribute("required");
          id_cuenta_valido.removeAttribute("required");
          id_cuenta_reviso.removeAttribute("required");
        });

        /*btn2.addEventListener("click", function () {
          observaciones.setAttribute("required", "required");
          id_cuenta_valido.setAttribute("required", "required");
          id_cuenta_reviso.setAttribute("required", "required");
        });

        // Validación general antes de enviar
        form.addEventListener("submit", function (event) {
          if (btn2.getAttribute("clicked") === "true" && !observaciones.value.trim()) {
            event.preventDefault();
            alert("Los datos de la revisión son obligatorios");
          }
        });*/

        // Marcar qué botón fue presionado
        btn1.addEventListener("click", () => btn2.removeAttribute("clicked"));
        //btn2.addEventListener("click", () => btn2.setAttribute("clicked", "true"));
      });
    </script>
    <style>
      .input-group {
        display: flex;
        align-items: center;
      }
      .input-group input {
        width: 100px;
      }
      .input-group button {
        margin-left: 10px;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper"><?php
      include('header.php');?>
	  
      <!-- Page Header Start-->
      <div class="page-body-wrapper"><?php
        include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Ver/Añadir Items Cómputo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion." N° ".$data["nro_computo"]." Rev. N° ".$data["nro_revision"]." (".$data["sitio"]."_".$data["subsitio"]."_".$data["nro_proyecto"].")"?></h5>
                  </div>
				          <form class="form theme-form" role="form" method="post" id="miFormulario" action="itemsComputo.php?modo=<?=$_GET['modo']?>&id=<?=$id?>">
                    <!-- Hidden inputs para los parámetros -->
                    <input type="hidden" name="modo"      value="<?= htmlspecialchars($modo) ?>">
                    <input type="hidden" name="id"        value="<?= htmlspecialchars($id) ?>">
                    <input type="hidden" name="revision"  value="<?= htmlspecialchars($revision) ?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-sm-12">
                              <table class="display" id="dataTables-example667">
                                <thead>
                                  <tr>
                                    <th>Concepto</th>
                                    <th>Cantidad</th>
                                    <th>Fecha Necesidad</th>
                                    <th>Aprobado</th>
                                    <th>Comentarios</th>
                                    <th>Opciones</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                $id_computo=$_GET['id'];
                                $pdo = Database::connect();
                                $sql = " SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad, date_format(cd.fecha_necesidad,'%d/%m/%y') AS fecha_necesidad_formatted, cd.aprobado, cd.comentarios, date_format(cd.fecha_necesidad,'%y%m%d') AS fecha_necesidad FROM computos_detalle cd inner join materiales m on m.id = cd.id_material WHERE cancelado = 0 and cd.id_computo = ".$id_computo;
                                $b=0;
                                foreach ($pdo->query($sql) as $row) {
                                  $b=1;
                                  $aprobado="No";
                                  if ($row["aprobado"] == 1) {
                                    $aprobado="Si";
                                  }?>
                                  <tr>
                                    <td><?=$row["concepto"]?></td>
                                    <td><?=$row["cantidad"]?></td>
                                    <td><span style="display: none;"><?=$row["fecha_necesidad"]?></span><?=$row["fecha_necesidad_formatted"]?></td>
                                    <td><?=$aprobado?></td>
                                    <td><?=$row["comentarios"]?></td>
                                    <td><?php
                                      if (!empty(tienePermiso(291))) {?>
                                        <a href="modificarItemComputo.php?id=<?=$row["id_computo_detalle"]?>&idRetorno=<?=$id_computo?>&modo=<?=$_GET['modo']?>&revision=<?=$_GET['revision']?>"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>
                                        &nbsp;&nbsp;
                                        <a href="#" data-toggle="modal" data-target="#eliminarModal_<?=$row["id_computo_detalle"]?>"><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>
                                        &nbsp;&nbsp;<?php
                                      }?>
                                    </td>
                                  </tr><?php
                                }
                                Database::disconnect();?>
                              </tbody>
                              <tfoot>
                                <tr>
                                  <th>Concepto</th>
                                  <th>Cantidad</th>
                                  <th>Fecha Necesidad</th>
                                  <th>Aprobado</th>
                                  <th>Comentarios</th>
                                  <th>Opciones</th>
                                </tr>
                              </tfoot>
                            </table>
                          </div>
							          </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Concepto(*)</label>
                          <div class="col-sm-9">
                            <select name="id_material" id="id_material" class="js-example-basic-single col-sm-12" required="required">
                              <option value="">Seleccione...</option><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $sqlZon = "SELECT `id`, `concepto`, `codigo` FROM `materiales` WHERE anulado = 0 ";
                              $q = $pdo->prepare($sqlZon);
                              $q->execute();
                              while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$fila['id']."'";
                                echo ">".$fila['concepto']." (".$fila['codigo'].")</option>";
                              }
                              Database::disconnect();?>
                            </select><?php
                            if (isset($_GET['error'])) { ?>
                              <div class="checkbox p-0">
                                <?php print("<b><font color='red'>No se puede agregar un concepto repetido!</font></b>");  ?>
                              </div><?php
                            } ?>
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                          <div class="col-sm-9">
                            <input name="cantidad" id="cantidad" step="0.01" min="0.01" type="number" class="form-control" required="required" value="">
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Fecha Necesidad(*)</label>
                          <div class="col-sm-9">
                            <input name="fecha_necesidad" id="fecha_necesidad" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="">
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-3 col-form-label">Comentarios</label>
                          <div class="col-sm-9">
                            <textarea name="comentarios" class="form-control"></textarea>
                          </div>
                          <input type="hidden" name="nro_revision" value="<?php if (!empty($_GET['revision'])) { echo $_GET['revision']; }else { echo "0"; } ?>">
                          <input type="hidden" name="modo" value="<?=$_GET['modo']; ?>">
                        </div>
                        <hr><?php
                        /*if ($_GET['modo']=="update") { ?>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Responsables de la revisión</label>
                            <div class="col-sm-9">
                              <div class="input-group">
                                <select name="id_cuenta_reviso" id="id_cuenta_reviso" class="form-control col-sm-12" required="required">
                                  <option value="">Cuenta Revisó...</option><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                                  $q = $pdo->prepare($sqlZon);
                                  $q->execute();
                                  while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='".$fila['id']."'";
                                    echo ">".$fila['nombre']."</option>";
                                  }
                                  Database::disconnect();?>
                                </select>
                                &nbsp;-&nbsp;
                                <select name="id_cuenta_valido" id="id_cuenta_valido" class="form-control col-sm-12" required="required">
                                  <option value="">Cuenta Aprobó...</option><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  $sqlZon = "SELECT `id`, `nombre` FROM `cuentas` WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
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
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Observaciones de la revisión</label>
                            <div class="col-sm-9">
                              <textarea name="observaciones" id="observaciones" class="form-control" required="required"></textarea>
                            </div>
                          </div><?php
                        }*/ ?>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-success" type="submit" value="1" name="btn1" id="btn1">Crear y Agregar Otro</button>
						            <!-- <button class="btn btn-primary" type="submit" value="2" name="btn2" id="btn2">Crear y Enviar a Aprobación</button> --><?php
                         if($b==1){?>
                          <button class="btn btn-primary" type="button" id="btnEnviarAprobacion">Enviar a aprobación</button><?php
                         }?>
						            <a href="listarComputos.php" class="btn btn-danger">Guardar y volver al Listado</a>

                        <!-- <button type="submit" name="btn1" class="btn btn-success">Crear y Agregar Otro</button>
                        <button type="submit" name="btn2" class="btn btn-primary">Enviar a aprobación</button>
                        <a href="listarComputos.php" class="btn btn-danger">Volver al Listado</a> -->

                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start--><?php
        include("footer.php"); ?>
      </div>
    </div>

    <!-- Modal de confirmación -->
    <div class="modal fade" id="modalEnviarAprobacion" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form id="formEnviarAprobacion" method="post" action="itemsComputo.php">
            <!-- mantenemos ocultos los campos esenciales -->
            <input type="hidden" name="modo"      value="<?= htmlspecialchars($modo) ?>">
            <input type="hidden" name="id"        value="<?= htmlspecialchars($id) ?>">
            <input type="hidden" name="revision"  value="<?= htmlspecialchars($revision) ?>">
            <input type="hidden" name="btn2_confirm" value="1">
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
    </div><?php

    $pdo = Database::connect();
    $sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado,d.id_computo FROM computos_detalle d inner join materiales m on m.id = d.id_material WHERE d.id_computo = ".$_GET['id'];
	  foreach ($pdo->query($sql) as $row) {?>
	    <div class="modal fade" id="eliminarModal_<?=$row["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
		    <div class="modal-dialog" role="document">
		      <div class="modal-content">
		        <div class="modal-header">
		          <h5 class="modal-title" id="exampleModalLabel">Confirmación</h5>
		          <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		        </div>
		        <div class="modal-body">¿Está seguro que desea eliminar el ítem del cómputo?</div>
		        <div class="modal-footer">
		          <a href="eliminarItemComputo.php?id=<?=$row["id"]?>&idComputo=<?=$row["id_computo"]?>&revision=<?php if (!empty($_GET['revision'])) { echo $_GET['revision']; }else { echo "0"; } ?>" class="btn btn-primary">Eliminar</a>
		          <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
		        </div>
		      </div>
		    </div>
	    </div><?php
	  }
	  Database::disconnect();?>
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
	  <!-- Plugins JS start-->
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

        // dentro de tu $(document).ready(...)
        $('#btnEnviarAprobacion').on('click', function(){
          let id_material=$("#id_material").val();
          let cantidad=$("#cantidad").val();
          let fecha_necesidad=$("#fecha_necesidad").val();

          if(id_material!="" || cantidad!="" || fecha_necesidad!=""){
            alert("No se puede enviar a aprobación si hay ítems sin guardar.");
            return false;
          }

          $('#modalEnviarAprobacion').modal('show');
        });

        // Setup - add a text input to each footer cell
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	    
        $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
          language: {
          "decimal": "",
          "emptyTable": "No hay información",
          "info": "Mostrando _START_ a _END_ de _TOTAL_ Registros",
          "infoEmpty": "Mostrando 0 to 0 of 0 Registros",
          "infoFiltered": "(Filtrado de _MAX_ total registros)",
          "infoPostFix": "",
          "thousands": ",",
          "lengthMenu": "Mostrar _MENU_ Registros",
          "loadingRecords": "Cargando...",
          "processing": "Procesando...",
          "search": "Buscar:",
          "zeroRecords": "No hay resultados",
          "paginate": {
              "first": "Primero",
              "last": "Ultimo",
              "next": "Siguiente",
              "previous": "Anterior"
          }}
        });
 
        // DataTable
        var table = $('#dataTables-example667').DataTable();
 
        // Apply the search
        table.columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        });
      });
    </script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>