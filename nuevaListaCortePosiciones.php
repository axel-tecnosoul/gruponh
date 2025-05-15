<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';

$id_lista_corte_conjunto = null;
if (!empty($_GET['id_lista_corte_conjunto'])) {
  $id_lista_corte_conjunto = $_REQUEST['id_lista_corte_conjunto'];
}

if (null==$id_lista_corte_conjunto) {
  header("Location: listarListasCorte.php");
}

if (!empty($_POST)) {
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_FILES);
  }

  if (!empty($_POST['btn3'])) {
    //editar posicion
    $id_lista_corte_posicion=$_POST['btn3'];

    $sql = "SELECT pos.cantidad, id_material, id_lista_corte_conjunto, id_lista_corte FROM lista_corte_posiciones pos INNER JOIN listas_corte_conjuntos lcc ON pos.id_lista_corte_conjunto=lcc.id WHERE pos.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte_posicion]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_lista_corte=$data['id_lista_corte'];
    $id_lista_corte_conjunto=$data['id_lista_corte_conjunto'];

    $sql = "UPDATE listas_corte_conjuntos set peso = peso - (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$data['cantidad'],$data['id_material'],$id_lista_corte_conjunto]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
    
    $sql = "UPDATE lista_corte_posiciones set largo=?,ancho=?,marca=?,peso=?,diametro=? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['largo'],$_POST['ancho'],$_POST['marca'],$_POST['peso'],$_POST['diametro'],$id_lista_corte_posicion]);

    if(isset($_POST["cantidad_posicion"]) and isset($_POST["nombre_posicion"])){
      $sql = "UPDATE lista_corte_posiciones set cantidad=?, posicion=? where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST['cantidad_posicion'],$_POST['nombre_posicion'],$id_lista_corte_posicion]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "DELETE from lista_corte_procesos WHERE id_lista_corte_posicion = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte_posicion]);
    
    //pasamos los procesos a un nuevo array y le agregamos el id_terminación que lo manejamos como un proceso mas
    $procesos=$_POST["proceso"];
    $procesos[]=$_POST["id_terminacion"];

    if ($modoDebug==1) {
      var_dump($procesos);
    }
    
    foreach ($procesos as $key => $id_proceso) {
      $observaciones="";

      $sql = "INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, id_estado_lista_corte_proceso, observaciones) VALUES (?,?,1,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_lista_corte_posicion,$id_proceso,$observaciones]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }

    $sql = "UPDATE listas_corte_conjuntos set peso = peso + (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['cantidad_posicion'],$_POST['id_material'],$id_lista_corte_conjunto]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
      
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Modificación de posición ID #$id_lista_corte_posicion en conjunto de lista de corte','Listas de Corte','')";
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
      header("Location: nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto);
      /*if (!empty($_POST['btn2'])) {
        header("Location: nuevoConjuntoListaCorte.php?id_lista_corte=".$id_lista_corte);
      } else {
        header("Location: nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto);
      }*/
    }

  }else{
    //insertar posicion
    $idColada = null;
    $sql = "SELECT col.id FROM coladas col inner join compras com on com.id = col.id_compra inner join pedidos p on p.id = com.id_pedido inner join computos c on c.id = p.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join listas_corte_revisiones lcr on lcr.id_proyecto = pr.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lcr.id WHERE col.id_material = ? and lcc.id = ? ";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['id_material'],$id_lista_corte_conjunto]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    if (!empty($data['id'])) {
      $idColada = $data['id'];	
    }

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
	
	//validacion de repetido
	$sqlP = " select count(*) cant from lista_corte_posiciones where posicion = ? and id_lista_corte_conjunto = ?";
    $qP = $pdo->prepare($sqlP);
    $qP->execute([$_POST['nombre_posicion'],$id_lista_corte_conjunto]);
    $dataP = $qP->fetch(PDO::FETCH_ASSOC);
	if ($dataP['cant'] == 0) {
		
		$calidad = "";
		if (!empty($_POST['id_material'])) {
			$sqlM = " select calidad from materiales where id = ?";
			$qM = $pdo->prepare($sqlM);
			$qM->execute([$_POST['id_material']]);
			$dataM = $qM->fetch(PDO::FETCH_ASSOC);
			$calidad = $dataM['calidad'];
		}
		
		$sql = "INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto, id_material, posicion, cantidad, largo, ancho, marca, peso, finalizado, id_colada, diametro, calidad) VALUES (?,?,?,?,?,?,?,?,0,?,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$id_lista_corte_conjunto,$_POST['id_material'],$_POST['nombre_posicion'],$_POST['cantidad_posicion'],$_POST['largo'],$_POST['ancho'],$_POST['marca'],$_POST['peso'],$idColada,$_POST['diametro'],$calidad]);
		$id_posicion = $pdo->lastInsertId();

		if ($modoDebug==1) {
		  $q->debugDumpParams();
		  echo "<br><br>Afe: ".$q->rowCount();
		  echo "<br><br>";
		}
		
		//pasamos los procesos a un nuevo array y le agregamos el id_terminación que lo manejamos como un proceso mas
		$procesos=$_POST["proceso"];
		$procesos[]=$_POST["id_terminacion"];

		if ($modoDebug==1) {
		  var_dump($procesos);
		}
		
		foreach ($procesos as $key => $id_proceso) {
		  $observaciones="";

		  $sql = "INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, id_estado_lista_corte_proceso, observaciones) VALUES (?,?,1,?)";
		  $q = $pdo->prepare($sql);
		  $q->execute([$id_posicion,$id_proceso,$observaciones]);

		  if ($modoDebug==1) {
			$q->debugDumpParams();
			echo "<br><br>Afe: ".$q->rowCount();
			echo "<br><br>";
		  }
		}

		$sql = "UPDATE listas_corte_conjuntos set peso = peso + (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['cantidad_posicion'],$_POST['id_material'],$id_lista_corte_conjunto]);

		if ($modoDebug==1) {
		  $q->debugDumpParams();
		  echo "<br><br>Afe: ".$q->rowCount();
		  echo "<br><br>";
		}

		$idComputoDetalle = 0;
		$sql = "SELECT cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte_revisiones lcr on lcr.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lcr.id where cd.cancelado = 0 and lcc.id = ? and m.id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$id_lista_corte_conjunto,$_POST['id_material']]);
		$data = $q->fetch(PDO::FETCH_ASSOC);
		$idComputoDetalle = $data['idComputoDetalle'];

		if ($modoDebug==1) {
		  $q->debugDumpParams();
		  echo "<br><br>Afe: ".$q->rowCount();
		  echo "<br><br>";
		}
		  
		$sql = "UPDATE computos_detalle set comprado = comprado + ?, reservado = reservado - ?  where id = ?";
		$q = $pdo->prepare($sql);
		$q->execute([$_POST['cantidad_posicion'],$_POST['cantidad_posicion'],$idComputoDetalle]);

		if ($modoDebug==1) {
		  $q->debugDumpParams();
		  echo "<br><br>Afe: ".$q->rowCount();
		  echo "<br><br>";
		}
		  
		$sql = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nueva Posición ID #$id_posicion de Concepto en Conjunto de Lista de Corte','Listas de Corte','')";
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
			$sql = "SELECT id_lista_corte FROM listas_corte_conjuntos WHERE id = ? ";
			$q = $pdo->prepare($sql);
			$q->execute([$id_lista_corte_conjunto]);
			$data = $q->fetch(PDO::FETCH_ASSOC);

			header("Location: nuevaListaCorteConjuntos.php?id_lista_corte_revision=".$data["id_lista_corte"]);
		  } else {
			header("Location: nuevaListaCortePosiciones.php?id_lista_corte_conjunto=".$id_lista_corte_conjunto);
		  }
		}
	} else {
		header("Location: nuevaListaCortePosiciones.php?error_repetido=1&id_lista_corte_conjunto=".$id_lista_corte_conjunto);
	}
      
    
  }
  
}

//$id_lista_corte_conjunto=$_GET['id_lista_corte_conjunto'];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT lcc.nombre,lcc.id_lista_corte,lcr.id_estado_lista_corte,lcr.id AS id_lista_corte_revision FROM listas_corte_conjuntos lcc INNER JOIN listas_corte_revisiones lcr ON lcc.id_lista_corte=lcr.id WHERE lcc.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte_conjunto]);
$data = $q->fetch(PDO::FETCH_ASSOC);
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
          $ubicacion="Nueva Posicion";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Posiciones para el Conjunto <?=$data['nombre']?>
                      &nbsp;&nbsp;<?php
                      /*if (!empty(tienePermiso(331))) {?>
                        <a href="nuevoPosicionListaCorte.php?id_lista_corte_conjunto=<?=$id_lista_corte_conjunto?>"><img src="img/icon_alta.png" width="24" height="25" border="0" alt="Nueva Posicion" title="Nueva Posicion"></a>&nbsp;&nbsp;<?php
                      }*/
                      if (!empty(tienePermiso(329))) {?>
                        <!-- <a href="#" id="link_modificar_posicion"><img src="img/icon_modificar.png" width="24" height="25" border="0" alt="Modificar" title="Modificar"></a>&nbsp;&nbsp; -->
                        <img src="img/icon_modificar.png" id="link_modificar_posicion" style="cursor: pointer;" width="24" height="25" border="0" alt="Modificar" title="Modificar">&nbsp;&nbsp;<?php
                      }
                      if (!empty(tienePermiso(330))) {?>
                        <a href="#" id="link_eliminar_posicion" data-id=""><img src="img/icon_baja.png" width="24" height="25" border="0" alt="Eliminar" title="Eliminar"></a>&nbsp;&nbsp;<?php
                      }
                      /*if (!empty(tienePermiso(331))) {?>
                        <a href="#" id="link_nueva_posicion"><img src="img/edit3.png" width="24" height="25" border="0" alt="Nueva Posición" title="Nueva Posición"></a>&nbsp;&nbsp;<?php
                      }*/?>
                      <!-- <a href="#" id="link_ver_posicion_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp; -->
                    </h5>
                  </div>
					        <form class="form theme-form" role="form" method="post" action="nuevaListaCortePosiciones.php?id_lista_corte_conjunto=<?=$id_lista_corte_conjunto?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="form-group col-12">
                          <div class="dt-ext table-responsive">
                            <table class="display" id="dataTables-example667">
                              <thead>
                                <tr>
                                  <th class="d-none">ID</th>
                                  <th>Posicion</th>
                                  <th>Cantidad</th>
                                  <th>Material</th>
                                  <th>Ancho</th>
                                  <th>Largo</th>
                                  <th>Diametro</th>
                                  <th>Marca</th>
                                  <th>Peso (Kg.)</th>
                                  <th>Procesos</th>
                                </tr>
                              </thead>
                              <tbody><?php
								$pesoTotal = 0;
								$pesoPosicion = 0;
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                
                                $sql = " SELECT pos.id, pos.posicion, pos.cantidad, m.concepto, pos.id_material, pos.ancho, pos.largo, pos.diametro, pos.marca, pos.peso, GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos, GROUP_CONCAT(tp.id SEPARATOR ',') AS id_procesos, pos.finalizado FROM lista_corte_posiciones pos inner join materiales m on m.id = pos.id_material LEFT JOIN lista_corte_procesos lcp ON lcp.id_lista_corte_posicion=pos.id LEFT JOIN tipos_procesos tp ON lcp.id_tipo_proceso=tp.id WHERE pos.id_lista_corte_conjunto = ".$id_lista_corte_conjunto." GROUP BY pos.id ";
                                foreach ($pdo->query($sql) as $row) {
                                  echo '<tr>';
                                  echo '<td class="d-none">'. $row["id"] . '</td>';
                                  echo '<td>'. $row["posicion"] . '</td>';
                                  echo '<td>'. $row["cantidad"] . '</td>';
                                  echo '<td data-id="'.$row["id_material"].'">'. $row["concepto"] . '</td>';
                                  echo '<td>'. $row["ancho"] . '</td>';
                                  echo '<td>'. $row["largo"] . '</td>';
                                  echo '<td>'. $row["diametro"] . '</td>';
                                  echo '<td>'. $row["marca"] . '</td>';
                                  echo '<td>'. $row["peso"] . '</td>';
                                  echo '<td data-id="'.$row["id_procesos"].'">'. $row["procesos"] . '</td>';
                                  echo '</tr>';
								  $pesoPosicion = $row["peso"];
								  if (str_starts_with($row["concepto"], "Chapa")) {
									if (empty($row["largo"]) && empty($row["ancho"])) {
										$pesoPosicion = $row["peso"]*$row["diametro"]*$row["diametro"];
									} else {
										$pesoPosicion = $row["peso"]*$row["largo"]*$row["ancho"]/1000;	
									}
								  }
								  if (str_starts_with($row["concepto"], "Perfil")) {
									$pesoPosicion = $row["peso"]*$row["largo"]/1000000;
								  }
								  //hacer la logica y sumar el peso
								  $pesoTotal += $pesoPosicion;
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
							<b>PESO TOTAL CONJUNTO:&nbsp;<?php echo number_format($pesoTotal,1);?>&nbsp;kgs</b>
                          </div>
                        </div>
                        <div class="form-group col-3">
                          <label>Posicion(*)</label>
                          <input name="nombre_posicion" type="text" maxlength="99" autofocus class="form-control nombre_posicion" required="required" value="">
						  <?php if (!empty($_GET['error_repetido'])) { echo "<font color='red'><b>El nombre de Posición utilizado ya está en uso</b></font>"; } ?>
						</div>
                        <div class="form-group col-3">
                          <label>Cantidad(*)</label>
                          <input name="cantidad_posicion" type="number" step="0.01" min="0.01" maxlength="99" class="form-control cantidad_posicion" required="required" value="">
                        </div>
                        <div class="form-group col-3">
                          <label>Concepto(*)</label><br>
                          <select name="id_material" class="js-example-basic-single id_material" onchange="jsCompletarPeso(this.value);">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT distinct m.id, m.codigo, m.concepto, c.nro_computo, max(c.nro_revision) from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte_revisiones lcr on lcr.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lcr.id where lcc.id = ".$id_lista_corte_conjunto." group by m.id, m.codigo, m.concepto, c.nro_computo ";
							//$sqlZon = "SELECT `id`, `concepto`, `codigo` FROM `materiales` WHERE anulado = 0 "; //que traiga los conceptos 
							$q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$fila['id']."'";
								echo ">".$fila['concepto']." (".$fila['codigo'].")</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="row">
                        <div class="form-group col-2">
                          <label>Ancho</label>
                          <input name="ancho" type="number" step="0.01" maxlength="99" class="form-control ancho" value="">
                        </div>
                        <div class="form-group col-2">
                          <label>Largo</label>
                          <input name="largo" type="number" step="0.01" maxlength="99" class="form-control largo" value="">
                        </div>
                        <div class="form-group col-2">
                          <label>Diametro</label>
                          <input name="diametro" type="number" step="0.01" maxlength="99" class="form-control diametro" value="">
                        </div>
                        <div class="form-group col-2">
                          <label>Marca</label>
                          <input name="marca" type="text" maxlength="99" class="form-control marca" value="">
                        </div>
                        <div class="form-group col-2">
                          <label>Peso KG x Metro</label>
                          <span id="pesokg"><input name="peso" type="number" step="0.01" maxlength="99" class="form-control peso" value=""></span>
                        </div>
                      </div>
                      <div class="row">
                        <div class="form-group col-9">
                          <label>Procesos(*)</label><br><?php
                          $pdo = Database::connect();
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $sqlZon = "SELECT id,tipo from tipos_procesos WHERE LENGTH(tipo)<=2";
                          $q = $pdo->prepare($sqlZon);
                          $q->execute();
                          while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                            $id="proceso_".$fila['id']?>
                          <div class="custom-control custom-checkbox d-inline-block pr-4">
                            <input type="checkbox" name="proceso[]" class="custom-control-input proceso" id="<?=$id?>" value="<?=$fila['id']?>">
                            <label class="custom-control-label" for="<?=$id?>"><?=$fila['tipo']?></label>
                          </div><?php
                          }
                          Database::disconnect();?>
                        </div>
                        <div class="form-group col-3">
                          <label>Terminación(*)</label><br>
                          <select name="id_terminacion" class="js-example-basic-single id_terminacion" required="required">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT id,tipo from tipos_procesos WHERE LENGTH(tipo)>2";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'>".$fila['tipo']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Crear y Agregar otra Posicion</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Crear y volver a Conjuntos</button>
                        <button type="submit" value="3" name="btn3" id="editPosicion" class="btn btn-primary d-none">Modificar</button>
                        <button type="button" id="cancelEditPosicion" class="btn btn-danger d-none">Cancelar Modificar</button>
                        <a href='nuevaListaCorteConjuntos.php?id_lista_corte_revision=<?=$data["id_lista_corte_revision"]?>' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas posiciones -->
        <div class="modal fade" id="eliminarPosicion" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar la posicion?</div>
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
        var id_estado_lista_corte="<?=$data["id_estado_lista_corte"]?>"
        // Setup - add a text input to each footer cell
        $('#dataTables-example667 tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      $('#dataTables-example667').DataTable({
          stateSave: false,
          responsive: false,
		  paging: false,
		  searching: false,
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
 
        //$('#dataTables-example667').find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#dataTables-example667 tbody tr td", function(){
          
          var t=$(this).parent();
          //t.parent().find("tr").removeClass("selected");

          let id_posicion=t.find("td:first-child").html();
          let nro_revision = t.find("td:nth-child(3)").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_modificar_posicion").attr("href","#");
            $("#link_eliminar_posicion").data("id","");
            $("#link_ver_posicion_lc").attr("href","#");
          }else{
            table.rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            $("#link_modificar_posicion").attr("href","modificarPosicionListaCorte.php?id="+id_posicion);
            $("#link_modificar_posicion").on("click",function(){
              let posicion = t.find("td:nth-child(2)").html();
              let cantidad = t.find("td:nth-child(3)").html();
              let id_material = t.find("td:nth-child(4)").data("id");
              let ancho = t.find("td:nth-child(5)").html();
              let largo = t.find("td:nth-child(6)").html();
              let diametro = t.find("td:nth-child(7)").html();
              let marca = t.find("td:nth-child(8)").html();
              let peso = t.find("td:nth-child(9)").html();
              let procesos = t.find("td:nth-child(10)").data("id");
              let aProcesos = procesos.split(",");

              let disablePosicion=false
              if(id_estado_lista_corte>1){
                disablePosicion=true
              }
              $("input[name='nombre_posicion']").val(posicion).attr("readonly",disablePosicion)
              $("input[name='cantidad_posicion']").val(cantidad).attr("readonly",disablePosicion)
              $("select[name='id_material']").val(id_material).trigger('change');
              $("input[name='ancho']").val(ancho).focus()
              $("input[name='largo']").val(largo)
              $("input[name='diametro']").val(diametro)
              $("input[name='marca']").val(marca)
              $("input[name='peso']").val(peso)
              $("input[name='proceso[]']").each(function(){
                this.checked=false;
                if (aProcesos.includes(this.value)) {
                  this.checked=true;
                }
              })
              var id_terminacion = $('select[name="id_terminacion"] option').map(function() {
                if (aProcesos.includes($(this).val())) {
                  return $(this).val()
                }
              }).get();
              $("select[name='id_terminacion']").val(id_terminacion).trigger('change');

              $("#editPosicion").val(id_posicion)
              if($("#editPosicion").hasClass("d-none")){
                $(".addPosicion").toggleClass("d-none")
                $("#editPosicion").toggleClass("d-none")
                $("#cancelEditPosicion").toggleClass("d-none")
              }
            })
            //$("#link_eliminar_posicion").attr("href","eliminarPosicionListaCorte.php?id="+id_posicion);
            $("#link_eliminar_posicion").data("id",id_posicion);
            $("#link_ver_posicion_lc").attr("href","verPosicionConjuntoListaCorte.php?id="+id_posicion);
          }
        });
    
      });

      $("#link_eliminar_posicion").on("click",function(){
        let id_posicion=$(this).data("id")
        if(id_posicion!="" && id_posicion>0){
          let modal=$("#eliminarPosicion")
          modal.modal("show")
          modal.find(".modal-footer a").attr("href","eliminarPosicionListaCorte.php?id="+id_posicion)
        }
      });

      $("#cancelEditPosicion").on("click",function(){
        $("input[name='nombre_posicion']").val("").attr("readonly",false)
        $("input[name='cantidad_posicion']").val("").attr("readonly",false)
        $("select[name='id_material']").val("").trigger('change');
        $("input[name='ancho']").val("")
        $("input[name='largo']").val("")
        $("input[name='diametro']").val("")
        $("input[name='marca']").val("")
        $("input[name='peso']").val("")
        $("input[name='proceso[]']").each(function(){
          this.checked=false;
        })
        $("select[name='id_terminacion']").val("").trigger('change');

        $(".addPosicion").toggleClass("d-none")
        $("#editPosicion").toggleClass("d-none")
        $("#editPosicion").val("")
        $("#cancelEditPosicion").toggleClass("d-none")
      })

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
	  
	  function jsCompletarPeso(val) {
		  $.ajax({
			type: "POST",
			url: "ajaxPeso.php",
			data: "id_concepto="+val,
			success: function(resp){
				$("#pesokg").html(resp);
			}
		});
	  }
      
    </script>
  </body>
</html>