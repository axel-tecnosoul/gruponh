<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';

    $id = null;
    if (!empty($_GET['id'])) {
        $id = $_REQUEST['id'];
    }
    
    if (null==$id) {
        header("Location: listarColadas.php");
    }
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE `coladas` set `cod_fabricante` = ?, `nro_colada` = ?, `fecha` = ?, `es_origen` = ? where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['cod_fabricante'], $_POST['nro_colada'], $_POST['fecha'], isset($_POST['es_origen']) ? 1 : 0, $_GET['id']]); 
    
    if (!empty($_POST['adjunto'])) {
          $sql = "update `coladas` set adjunto = ? where id = ?";
          $q = $pdo->prepare($sql);
          $q->execute(array($_POST['adjunto'],$_GET['id']));
        } 

        $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de colada','Coladas','verColada.php?id=$id')";
        $q = $pdo->prepare($sql);
        $q->execute(array($_SESSION['user']['id']));
        
        Database::disconnect();
        
        header("Location: listarColadas.php");
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT `id`, `id_material`, `id_proveedor`, `id_compra`, `cod_fabricante`, `nro_colada`, `adjunto`, `fecha`, `es_origen` FROM `coladas` WHERE id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);

        $sql = "SELECT id, nro_colada_interna, cantidad, saldo FROM ingresos_detalle WHERE id_colada_origen = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $details = $q->fetchAll(PDO::FETCH_ASSOC);
        
        Database::disconnect();
    }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
  </head>
  <body>
    <div class="page-wrapper">
    <?php include('header.php');?>
    
      <div class="page-body-wrapper">
    <?php include('menu.php');?>
        <div class="page-body"><?php
          $ubicacion="Actualizar Colada";
          include_once("head_page.php")?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header pedido-summary">
                    <h5>
                      <?=$ubicacion?>
                      <?php if (!empty($data['adjunto'])) { ?>
                        <a href="<?php echo $data['adjunto'];?>" target="_blank" title="Descargar Certificado" style="margin-left: 10px; vertical-align: middle;">
                          <i data-feather="download" style="width: 20px; height: 20px; color: #242934; stroke-width: 2.5;"></i>
                        </a>
                      <?php } ?>
                    </h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="modificarColada.php?id=<?php echo $id?>" enctype="multipart/form-data">
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-12">
                          <h6 class="mb-3 font-weight-bold">Datos de la Colada</h6>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Código Fabricante(*)</label>
                            <div class="col-sm-9"><input name="cod_fabricante" type="text" maxlength="99" class="form-control" autofocus value="<?php echo $data['cod_fabricante']; ?>" required="required"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Nro. Colada(*)</label>
                            <div class="col-sm-9"><input name="nro_colada" type="text" maxlength="99" class="form-control" value="<?php echo $data['nro_colada']; ?>" required="required"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Adjuntar nuevo Certificado(*)</label>
                            <div class="col-sm-9"><input name="adjunto" type="text" value="<?php echo $data['adjunto']; ?>" class="form-control"></div>
                            <input type="hidden" name="hId" value="1" />
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha</label>
                            <div class="col-sm-9">
                              <input name="fecha" type="date" class="form-control" value="<?php echo $data['fecha']; ?>" required>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Colada de Origen</label>
                            <div class="col-sm-9 col-form-label">
                              <?php echo $data['es_origen'] ? 'Sí' : 'No'; ?>
                              <input type="hidden" name="es_origen" value="<?php echo $data['es_origen']; ?>">
                            </div>
                          </div>
                        </div>
                      </div>

                      <hr class="mt-4 mb-4">
                      
                      <div class="row">
                        <div class="col-sm-12">
                          <h6 class="mb-3 font-weight-bold">Detalle de la Colada de Origen</h6>
                          <div class="table-responsive">
                            <table class="display" id="dataTables-example667" style="width:100%">
                              <thead>
                                <tr>
                                  <th>ID</th>
                                  <th>Nro. Colada Interna</th>
                                  <th>Cantidad</th>
                                  <th>Saldo</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($details as $detail) { ?>
                                <tr>
                                  <td><?=$detail['id']?></td>
                                  <td><?=$detail['nro_colada_interna']?></td>
                                  <td><?=$detail['cantidad']?></td>
                                  <td><?=$detail['saldo']?></td>
                                </tr>
                                <?php } ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                      </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3 text-center">
                        <button class="btn btn-primary" type="submit">Actualizar</button>
                        <a onclick="document.location.href='listarColadas.php'" class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          </div>
        <?php include("footer.php"); ?>
      </div>
    </div>
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
    
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
    <script src="assets/js/script.js"></script>

    <script>
      $(document).ready(function() {
        // Inicialización DataTables con el estilo del segundo componente
        $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
          scrollX: false,
          scrollCollapse: false,
          autoWidth: false,
          paging: true,
          pageLength: 10,
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
  </body>
</html>