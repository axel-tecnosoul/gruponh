<?php
require("config.php");
require("PHPMailer/class.phpmailer.php");
require("PHPMailer/class.smtp.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
	
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT valor FROM parametros WHERE id = 8 ";
$q = $pdo->prepare($sql);
$q->execute();
$data = $q->fetch(PDO::FETCH_ASSOC);
$direccion = $data['valor'];
    
if (!empty($_POST)) {

  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $sql = "INSERT INTO pedidos(id_computo, fecha, lugar_entrega, id_cuenta_recibe, id_estado) VALUES (?,?,?,?,1)";
  $q = $pdo->prepare($sql);
  $q->execute([$_GET['idComputo'],$_POST['fecha'],$_POST['lugar_entrega'],$_POST['id_cuenta_recibe']]);
      
  $id = $pdo->lastInsertId();

  //$sql = " SELECT d.id, m.concepto, d.cantidad, d.fecha_necesidad, d.aprobado, d.id_material, d.reservado, d.comprado,s.disponible,m.id_unidad_medida FROM computos_detalle d inner join materiales m on m.id = d.id_material left join stock s on s.id_material = d.id_material WHERE d.id_computo = ".$_GET['idComputo'];		
  $sql = " SELECT d.id, m.concepto, d.cantidad, d.fecha_necesidad, d.aprobado, d.id_material, d.reservado, d.comprado,SUM(id.saldo) AS disponible,m.id_unidad_medida FROM computos_detalle d inner join materiales m on m.id = d.id_material left join ingresos_detalle id on id.id_material = d.id_material WHERE d.cancelado = 0 and d.id_computo = ".$_GET['idComputo']." GROUP BY d.id_material";
  foreach ($pdo->query($sql) as $row) {
    $cantidad = $_POST['cantidad_'.$row[0]];
    if ($cantidad > 0) {
      $sql = "INSERT INTO pedidos_detalle(id_pedido, id_material, fecha_necesidad, cantidad, id_unidad_medida,reservado,comprado) VALUES (?,?,?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id,$row[5],$row[3],$cantidad,$row[9],$row[6],$row[7]]);
    }	
  }

  $sql = "UPDATE computos SET id_estado = 4 WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$_GET['idComputo']]);

  $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Pedido','Pedidos','verPedido.php?id=$id')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  $sql = "SELECT valor FROM parametros WHERE id = 1 ";
  $q = $pdo->prepare($sql);
  $q->execute();
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $smtpHost = $data['valor'];

  $sql = "SELECT valor FROM parametros WHERE id = 2 ";
  $q = $pdo->prepare($sql);
  $q->execute();
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $smtpUsuario = $data['valor'];

  $sql = "SELECT valor FROM parametros WHERE id = 3 ";
  $q = $pdo->prepare($sql);
  $q->execute();
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $smtpClave = $data['valor'];

  $sql = "SELECT valor FROM parametros WHERE id = 4 ";
  $q = $pdo->prepare($sql);
  $q->execute();
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $smtpFrom = $data['valor'];

  $sql = "SELECT valor FROM parametros WHERE id = 5 ";
  $q = $pdo->prepare($sql);
  $q->execute();
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $smtpFromName = $data['valor'];

  $sql = " SELECT t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 10 ";
  foreach ($pdo->query($sql) as $row) {
    
    $sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (10,?,now(),0,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$row[0],'ID Pedido: #'.$id,$id]);
    
    $address = $row[1];
    
    $titulo = "ERP Notificaciones - Módulo Compras - Nuevo Pedido";
    $mensaje="Nuevo pedido dado de alta en el sistema: #".$id;
    
    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->SMTPAuth = true;
    $mail->Port = 25; 
    $mail->SMTPSecure = 'ssl';
    $mail->SMTPAutoTLS = false;
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

  }

  Database::disconnect();
  header("Location: listarPedidos.php");
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
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
          $ubicacion="Nuevo Pedido";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
				          <form class="form theme-form" role="form" method="post" action="nuevoPedido.php?idComputo=<?php echo $_GET['idComputo'];?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Pedido(*)</label>
                            <div class="col-sm-9">
                              <input name="fecha" type="date" autofocus onfocus="this.showPicker()" value="<?php echo date('Y-m-d');?>" class="form-control" required="required">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Lugar de Entrega(*)</label>
                            <div class="col-sm-9">
                              <input name="lugar_entrega" type="text" maxlength="199" class="form-control" required="required" value="<?php echo $direccion;?>">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Recibe(*)</label>
                            <div class="col-sm-9">
                              <select name="id_cuenta_recibe" id="id_cuenta_recibe" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
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
                          <div class="form-group row">
                            <div class="col-sm-12">
                              <table class="display" id="dataTables-example667">
                                <thead>
                                  <tr>
                                    <th>Concepto</th>
                                    <th>Fecha Necesidad</th>
                                    <th>Cantidad</th>
                                  </tr>
                                </thead>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  //$sql = " SELECT d.`id`, m.`concepto`, d.`cantidad`, date_format(d.`fecha_necesidad`,'%d/%m/%y'), d.`aprobado`, d.id_material, d.`reservado`, d.`comprado`,s.disponible FROM `computos_detalle` d inner join materiales m on m.id = d.id_material left join stock s on s.id_material = d.id_material WHERE d.id_computo = ".$_GET['idComputo'];
                                  $sql = " SELECT d.id, m.concepto, d.cantidad, date_format(d.fecha_necesidad,'%d/%m/%y'), d.aprobado, d.id_material, d.reservado, d.comprado,SUM(id.saldo) AS disponible FROM computos_detalle d inner join materiales m on m.id = d.id_material left join ingresos_detalle id on id.id_material = d.id_material WHERE d.cancelado = 0 and d.id_computo = ".$_GET['idComputo']." GROUP BY d.id_material";
                                  
                                  foreach ($pdo->query($sql) as $row) {
                                    $cantidad = $_GET['cantidad_'.$row[0]];
                                    if ($cantidad > 0) {
                                      echo '<tr>';
                                      echo '<td>'. $row[1] . '</td>';
                                      echo '<td>'. $row[3] . '</td>';
                                      echo '<td><input name="cantidad_'.$row[0].'" type="number" step="0.01" value="'.$cantidad.'" class="form-control" readonly="readonly"></td>';
                                      echo '</tr>';
                                    }
                                  }
                                  Database::disconnect();?>
								                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Crear</button>
						            <a href="listarPedidos.php" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
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
            }
          }
        });
      });
		</script>
		<script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
    <!-- Plugin used-->
	  <!-- Page-Level Demo Scripts - Tables - Use for reference -->
  </body>
</html>