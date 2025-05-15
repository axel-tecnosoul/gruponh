<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';

$id_packing_list_seccion = null;
if (!empty($_GET['id_packing_list_seccion'])) {
  $id_packing_list_seccion = $_REQUEST['id_packing_list_seccion'];
}

if (null==$id_packing_list_seccion) {
  header("Location: listarPackingList.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "select plr.id_proyecto from packing_lists_secciones pls inner join packing_lists_revisiones plr on plr.id = pls.id_packing_list_revision where pls.id = ".$id_packing_list_seccion;
$q = $pdo->prepare($sql);
$q->execute();
$data = $q->fetch(PDO::FETCH_ASSOC);
$idProyecto=$data['id_proyecto'];

if (!empty($_POST)) {
  // insert data
  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_FILES);
  }

  $sql = "SELECT plr.id_packing_list FROM packing_lists_secciones pls INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id WHERE pls.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_packing_list_seccion]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_packing_list=$data['id_packing_list'];

  if (!empty($_POST['btn3'])) {
	  
	if (empty($_POST['id_concepto'])) {
		$_POST['id_concepto'] = null;
	}
	if (empty($_POST['id_conjunto_lista_corte'])) {
		$_POST['id_conjunto_lista_corte'] = null;
	}
    //editar componente
    $id_packing_list_componente=$_POST['btn3'];
    
    $sql = "UPDATE packing_lists_componentes set id_conjunto_lista_corte=?, id_concepto=?, cantidad=?, observaciones=? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['id_conjunto_lista_corte'],$_POST['id_concepto'],$_POST['cantidad'],$_POST['observaciones'],$id_packing_list_componente]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql="SELECT cd.id AS idComputoDetalle FROM packing_lists_componentes plc INNER JOIN packing_lists_secciones pls ON plc.id_packing_list_seccion=pls.id INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id INNER JOIN proyectos p ON plr.id_proyecto=p.id INNER JOIN tareas t ON t.id_proyecto=p.id INNER JOIN computos c ON c.id_tarea=t.id INNER JOIN computos_detalle cd ON cd.id_computo=c.id WHERE plc.id = ? AND cd.cancelado = 0 and cd.id_material = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_packing_list_componente,$_POST['id_concepto']]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $idComputoDetalle = $data['idComputoDetalle'];

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    if($idComputoDetalle>0){
      $sql = "UPDATE packing_lists_componentes SET id_computo_detalle = ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute(array($idComputoDetalle,$id_packing_list_componente));

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }
      
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de Componente ID #$id_packing_list en conjunto de lista de corte','Listas de Corte','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
      
    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    } else {
      Database::disconnect();
      header("Location: nuevaPackingListComponentes.php?id_packing_list_seccion=".$id_packing_list_seccion);
      
    }

  }else{
    //insertar componente

    $idComputoDetalle = null;
	$errorDobleSeleccion = 0;
	$errorRepetido = 0;
	if ((empty($_POST['id_concepto'])) && (empty($_POST['id_conjunto_lista_corte']))) {
		$errorDobleSeleccion = 1;
	} else if ((!empty($_POST['id_concepto'])) && (!empty($_POST['id_conjunto_lista_corte']))) {
		$errorDobleSeleccion = 1;
	} else {
		if (empty($_POST['id_concepto'])) {
			$_POST['id_concepto'] = null;
		}
		if (empty($_POST['id_conjunto_lista_corte'])) {
			$_POST['id_conjunto_lista_corte'] = null;
		}
		
		$sql44="select count(*) cant from packing_lists_componentes where id_conjunto_lista_corte = ? and id_packing_list_seccion = ?";
		$q44 = $pdo->prepare($sql44);
		$q44->execute([$_POST['id_conjunto_lista_corte'],$id_packing_list_seccion]);
		$data44 = $q44->fetch(PDO::FETCH_ASSOC);
		if ($data44['cant'] > 0) {
			$errorRepetido = 1;
		} else {
			$sql = "INSERT INTO packing_lists_componentes (id_packing_list_seccion, id_conjunto_lista_corte, id_concepto, cantidad, id_computo_detalle, observaciones, id_estado_componente_packing_list) VALUES (?,?,?,?,?,?,1)";
			$q = $pdo->prepare($sql);
			$q->execute([$id_packing_list_seccion,$_POST['id_conjunto_lista_corte'],$_POST['id_concepto'],$_POST['cantidad'], $idComputoDetalle,$_POST['observaciones']]);
			$id_componente = $pdo->lastInsertId();

			if ($modoDebug==1) {
			  $q->debugDumpParams();
			  echo "<br><br>Afe: ".$q->rowCount();
			  echo "<br><br>";
			}

			//$sql = "SELECT cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join packing_lists_revisiones plr on plr.id_proyecto = p.id inner join packing_lists_secciones pls on pls.id_packing_list_revision = plr.id where pls.id = ? and m.id = ?";
			$sql="SELECT cd.id AS idComputoDetalle FROM packing_lists_componentes plc INNER JOIN packing_lists_secciones pls ON plc.id_packing_list_seccion=pls.id INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id INNER JOIN proyectos p ON plr.id_proyecto=p.id INNER JOIN tareas t ON t.id_proyecto=p.id INNER JOIN computos c ON c.id_tarea=t.id INNER JOIN computos_detalle cd ON cd.id_computo=c.id WHERE cd.cancelado = 0 and plc.id = ? AND cd.id_material = ?";
			$q = $pdo->prepare($sql);
			$q->execute([$id_componente,$_POST['id_concepto']]);
			$data = $q->fetch(PDO::FETCH_ASSOC);
			$idComputoDetalle = $data['idComputoDetalle'];

			if ($modoDebug==1) {
			  $q->debugDumpParams();
			  echo "<br><br>Afe: ".$q->rowCount();
			  echo "<br><br>";
			}

			if($idComputoDetalle>0){
			  $sql = "UPDATE packing_lists_componentes SET id_computo_detalle = ? WHERE id = ?";
			  $q = $pdo->prepare($sql);
			  $q->execute(array($idComputoDetalle,$id_componente));

			  if ($modoDebug==1) {
				$q->debugDumpParams();
				echo "<br><br>Afe: ".$q->rowCount();
				echo "<br><br>";
			  }
			}
			  
			$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Componente ID #$id_componente en Seccion de Packing List','Packing List','verPackingList.php?id=$id_packing_list')";
			$q = $pdo->prepare($sql);
			$q->execute(array($_SESSION['user']['id']));

			if ($modoDebug==1) {
			  $q->debugDumpParams();
			  echo "<br><br>Afe: ".$q->rowCount();
			  echo "<br><br>";
			}
			  
			if ($modoDebug==1) {
			  $pdo->rollBack();
			  die();
			} else {
			  Database::disconnect();
			  if (!empty($_POST['btn2'])) {
				$sql = "SELECT id_packing_list_revision FROM packing_lists_secciones WHERE id = ? ";
				$q = $pdo->prepare($sql);
				$q->execute([$id_packing_list_seccion]);
				$data = $q->fetch(PDO::FETCH_ASSOC);

				header("Location: nuevaPackingListSecciones.php?id_packing_list_revision=".$data["id_packing_list_revision"]);
			  } else {
				header("Location: nuevaPackingListComponentes.php?id_packing_list_seccion=".$id_packing_list_seccion);
			  }
			}	
		}
		
		
	}

    
  }
  
}

//$id_packing_list_seccion=$_GET['id_packing_list_seccion'];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT pls.id AS id_packing_list_seccion,pls.id_packing_list_revision,plr.id AS id_packing_list,pls.observaciones,plr.id_proyecto FROM packing_lists_secciones pls INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id WHERE pls.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_packing_list_seccion]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$nombreSeccion=$data['observaciones'];
$idProyecto=$data['id_proyecto'];
Database::disconnect();
?>
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
          $ubicacion="Nuevo Componente";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Componentes para la Seccion: <?=$nombreSeccion?>
                      &nbsp;&nbsp;<?php
                      /*if (!empty(tienePermiso(331))) {?>
                        <a href="nuevoComponentePackingList.php?id_packing_list_seccion=<?=$id_packing_list_seccion?>"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nuevo Componente" title="Nuevo Componente"></a>&nbsp;&nbsp;<?php
                      }*/
                      if (!empty(tienePermiso(329))) {?>
                        <!-- <a href="#" id="link_modificar_componente"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp; -->
                        <img src="img/icon_modificar.png" id="link_modificar_componente" style="cursor: pointer;" width="24" height="25" border="0" alt="Modificar" title="Modificar">&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(330))) {?>
                        <a href="#" id="link_eliminar_componente" data-id=""><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      /*if (!empty(tienePermiso(331))) {?>
                        <a href="#" id="link_nuevo_componente"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nuevo Componente" title="Nuevo Componente"></a>&nbsp;&nbsp;<?php
                      }*/?>
                      <!-- <a href="#" id="link_ver_componente_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp; -->
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevaPackingListComponentes.php?id_packing_list_seccion=<?=$id_packing_list_seccion?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="form-group col-12">
                          <div class="dt-ext table-responsive">
                            <table class="display" id="dataTables-example667">
                              <thead>
                                <tr>
                                  <th class="d-none">ID</th>
                                  <th>Concepto</th>
                                  <th>Conjunto</th>
                                  <th>Cantidad</th>
                                  <th>Observaciones</th>
                                </tr>
                              </thead>
                              <tfoot>
                                <tr>
                                  <th class="d-none">ID</th>
                                  <th>Concepto</th>
                                  <th>Conjunto</th>
                                  <th>Cantidad</th>
                                  <th>Observaciones</th>
                                </tr>
                              </tfoot>
                              <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                $sql = " SELECT plc.id,m.id AS id_concepto,m.concepto,lcc.id AS id_conjunto,lcc.nombre AS conjunto,plc.cantidad,plc.observaciones FROM packing_lists_componentes plc LEFT JOIN materiales m ON plc.id_concepto=m.id LEFT JOIN listas_corte_conjuntos lcc ON plc.id_conjunto_lista_corte=lcc.id WHERE plc.id_packing_list_seccion = ".$id_packing_list_seccion;
                                foreach ($pdo->query($sql) as $row) {
                                  echo '<tr>';
                                  echo '<td class="d-none">'.$row["id"].'</td>';
                                  echo '<td data-id="'.$row["id_concepto"].'">'.$row["concepto"].'</td>';
                                  echo '<td data-id="'.$row["id_conjunto"].'">'.$row["conjunto"].'</td>';
                                  echo '<td>'.$row["cantidad"].'</td>';
                                  echo '<td>'.$row["observaciones"].'</td>';
                                  echo '</tr>';
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="form-group col-sm-6">
                          <label for="id_concepto">Concepto</label>
                          <select name="id_concepto" id="id_concepto" class="js-example-basic-single col-sm-12" autofocus>
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							$sqlZon ="SELECT distinct m.id, m.codigo, m.concepto, cd.cantidad from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join packing_lists_revisiones plr on plr.id_proyecto = p.id INNER JOIN packing_lists pl ON plr.id_packing_list=pl.id AND pl.ultimo_nro_revision=plr.nro_revision inner join packing_lists_secciones pls on pls.id_packing_list_revision = plr.id where cd.cancelado = 0 and p.id = ".$idProyecto;
							$q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$fila['id']."'";
							    echo ">".$fila['concepto']." (".$fila['codigo'].") x".$fila['cantidad']." </option>";	
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                        <div class="form-group col-sm-6">
                          <label for="id_conjunto_lista_corte">Conjunto de LC</label>
                          <div class="col-sm-9">
                            <select name="id_conjunto_lista_corte" id="id_conjunto_lista_corte" class="js-example-basic-single col-sm-12" onchange="jsCompletarCant(this.value);">
                              <option value="">Seleccione...</option><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              $sqlZon = "SELECT distinct lcc.`id`, lcc.`nombre`, lcc.`cantidad` FROM `listas_corte_conjuntos` lcc inner join listas_corte_revisiones lcr on lcr.id = lcc.id_lista_corte inner join proyectos p on p.id = lcr.id_proyecto WHERE lcr.id_estado_lista_corte in (1,2,3,4) and lcr.id_proyecto = ".$idProyecto;
                              $q = $pdo->prepare($sqlZon);
                              $q->execute();
                              while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$fila['id']."'";
                                echo ">".$fila['nombre']." (".$fila['cantidad'].")</option>";
                              }
                              Database::disconnect();?>
                            </select>
							<?php if ((isset($errorRepetido)) && ($errorRepetido == 1)) { ?>
							<div class="form-group col-sm-12 checkbox p-0">
							  <?php print("<b><font color='red'>Conjunto de LC repetido en esta sección!</font></b>");  ?>
							  <br>
							</div>
							<?php } ?>
                          </div>
                        </div>
						<?php if ((isset($errorDobleSeleccion)) && ($errorDobleSeleccion == 1)) { ?>
						<div class="form-group col-sm-12 checkbox p-0">
						  <?php print("<b><font color='red'>Seleccione Concepto o Conjunto de LC</font></b>");  ?>
						  <br>
						</div>
						<?php } ?>
                        <div class="form-group col-sm-6">
                          <label for="cantidad">Cantidad(*)</label>
                          <span id="cantID"><input name="cantidad" id="cantidad" type="number" step="0.01" class="form-control" required="required"></span>
                        </div>
                        <div class="form-group col-sm-6">
                          <label for="observaciones">Observaciones</label>
                          <textarea name="observaciones" id="observaciones" class="form-control"></textarea>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" value="1" name="btn1" class="btn btn-success addComponente">Crear y Agregar otro Componente</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addComponente">Crear y volver a Secciones</button>
                        <button type="submit" value="3" name="btn3" id="editComponente" class="btn btn-primary d-none">Modificar</button>
                        <button type="button" id="cancelEditComponente" class="btn btn-danger d-none">Cancelar Modificar</button>
                        <a href='nuevaPackingListSecciones.php?id_packing_list_revision=<?=$data["id_packing_list"]?>' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas componentes -->
        <div class="modal fade" id="eliminarComponente" tabindex="-1" role="dialog" aria-labelledby="exampleModalComponenteLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalComponenteLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el componente?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
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
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script>
      $(document).ready(function () {
        // Setup - add a text input to each footer cell
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
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
        } );

        //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#dataTables-example667 tbody tr td", function(){
          var t=$(this).parent();
          //t.parent().find("tr").removeClass("selected");

          let id_componente=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_modificar_componente").attr("href","#");
            $("#link_eliminar_componente").data("id","");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#link_modificar_componente").attr("href","modificarComponentePackingList.php?id="+id_componente);
            $("#link_modificar_componente").on("click",function(){
              let id_concepto = t.find("td:nth-child(2)").data("id");
              let id_conjunto = t.find("td:nth-child(3)").data("id");
              let cantidad = t.find("td:nth-child(4)").html();
              let observaciones = t.find("td:nth-child(5)").html();

              let disableComponente=false
              /*if(id_estado_lista_corte>1){
                disableComponente=true
              }*/
              $("select[name='id_concepto']").val(id_concepto).trigger('change');
              $("select[name='id_conjunto_lista_corte']").val(id_conjunto).trigger('change');
              $("input[name='cantidad']").val(cantidad).focus()
              $("textarea[name='observaciones']").val(observaciones)

              $("#editComponente").val(id_componente)
              if($("#editComponente").hasClass("d-none")){
                $(".addComponente").toggleClass("d-none")
                $("#editComponente").toggleClass("d-none")
                $("#cancelEditComponente").toggleClass("d-none")
              }
            })
            //$("#link_eliminar_componente").attr("href","eliminarComponentePackingList.php?id="+id_componente);
            $("#link_eliminar_componente").data("id",id_componente);
          }
        });
    
      });

      $("#link_eliminar_componente").on("click",function(){
        let id_componente=$(this).data("id")
        if(id_componente!="" && id_componente>0){
          let modal=$("#eliminarComponente")
          modal.modal("show")
          modal.find(".modal-footer a").attr("href","eliminarComponentePackingList.php?id="+id_componente)
        }
      });

      $("#cancelEditComponente").on("click",function(){
        $("select[name='id_concepto']").val("").trigger('change');
        $("select[name='id_conjunto_lista_corte']").val("").trigger('change');
        $("input[name='cantidad']").val("")
        $("textarea[name='observaciones']").val("")

        $(".addComponente").toggleClass("d-none")
        $("#editComponente").toggleClass("d-none")
        $("#editComponente").val("")
        $("#cancelEditComponente").toggleClass("d-none")
      })

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
	  
	  function jsCompletarCant(val) {
		  $.ajax({
			type: "POST",
			url: "ajaxCant.php",
			data: "id_conjunto="+val,
			success: function(resp){
				$("#cantID").html(resp);
			}
		});
	  }
      
    </script>
  </body>
</html>