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

  $nombre_posicion=trim($_POST['nombre_posicion']);
  $id_material=trim($_POST['id_material']);
  $cantidad_posicion=trim($_POST['cantidad_posicion']);
  $largo=trim($_POST['largo']);
  $ancho=trim($_POST['ancho']);
  $marca=trim($_POST['marca']);
  $peso=trim($_POST['peso']);
  $peso_calculado_posicion=trim($_POST['hiddenPesoCalculado']);
  $diametro=trim($_POST['diametro']);

  if (!empty($_POST['btn3'])) {
    //editar posicion
    $id_lista_corte_posicion=$_POST['btn3'];

    $sql = "SELECT pos.cantidad, id_material, id_lista_corte_conjunto, id_lista_corte FROM lista_corte_posiciones pos INNER JOIN listas_corte_conjuntos lcc ON pos.id_lista_corte_conjunto=lcc.id WHERE pos.id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte_posicion]);
    $data = $q->fetch(PDO::FETCH_ASSOC);
    $id_lista_corte=$data['id_lista_corte'];
    $id_lista_corte_conjunto=$data['id_lista_corte_conjunto'];

    /*$sql = "UPDATE listas_corte_conjuntos set peso = peso - (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$data['cantidad'],$data['id_material'],$id_lista_corte_conjunto]);*/

    //recalculo de colada y calidad del nuevo material
    $idColada = null;
    $sql = "SELECT col.id FROM coladas col inner join compras com on com.id = col.id_compra inner join pedidos p on p.id = com.id_pedido inner join computos c on c.id = p.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = pr.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id WHERE col.id_material = ? and lcc.id = ? ";
    $qCol = $pdo->prepare($sql);
    $qCol->execute([$id_material,$id_lista_corte_conjunto]);
    $dataCol = $qCol->fetch(PDO::FETCH_ASSOC);
    if (!empty($dataCol['id'])) {
      $idColada = $dataCol['id'];
    }

    $calidad = null;
    if (!empty($id_material)) {
      $sqlM = " SELECT calidad from materiales where id = ?";
      $qM = $pdo->prepare($sqlM);
      $qM->execute([$id_material]);
      $dataM = $qM->fetch(PDO::FETCH_ASSOC);
      $calidad = $dataM['calidad'];
    }

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "UPDATE lista_corte_posiciones set id_material=?, largo=?, ancho=?, marca=?, peso=?, peso_calculado_posicion=?, diametro=?, id_colada=?, calidad=? where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_material,$largo,$ancho,$marca,$peso,$peso_calculado_posicion,$diametro,$idColada,$calidad,$id_lista_corte_posicion]);

    if(isset($cantidad_posicion) and isset($nombre_posicion)){
      $sql = "UPDATE lista_corte_posiciones set cantidad=?, posicion=? where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$cantidad_posicion,$nombre_posicion,$id_lista_corte_posicion]);

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

    $sql = "UPDATE listas_corte_conjuntos set peso = (SELECT SUM(peso_calculado_posicion) FROM lista_corte_posiciones WHERE id_lista_corte_conjunto = ?) where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte_conjunto,$id_lista_corte_conjunto]);

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
    //$sql = "SELECT col.id FROM coladas col inner join compras com on com.id = col.id_compra inner join pedidos p on p.id = com.id_pedido inner join computos c on c.id = p.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join listas_corte_revisiones lcr on lcr.id_proyecto = pr.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lcr.id WHERE col.id_material = ? and lcc.id = ? ";
    $sql = "SELECT col.id FROM coladas col inner join compras com on com.id = col.id_compra inner join pedidos p on p.id = com.id_pedido inner join computos c on c.id = p.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = pr.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id WHERE col.id_material = ? and lcc.id = ? ";
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

    $calidad = null;
    if (!empty($id_material)) {
      $sqlM = " SELECT calidad from materiales where id = ?";
      $qM = $pdo->prepare($sqlM);
      $qM->execute([$id_material]);
      $dataM = $qM->fetch(PDO::FETCH_ASSOC);
      $calidad = $dataM['calidad'];
    }
	
	  //validacion de repetido
	  $sqlP = " SELECT count(*) cant from lista_corte_posiciones where posicion = ? and id_lista_corte_conjunto = ? AND id_material = ? AND cantidad = ? AND largo = ? AND ancho = ? AND marca = ? AND peso = ? AND diametro = ?";// AND id_colada = ? AND calidad = ?
    $qP = $pdo->prepare($sqlP);
    $params = [$nombre_posicion,$id_lista_corte_conjunto,$id_material,$cantidad_posicion,$largo,$ancho,$marca,$peso,$diametro];//,$idColada,$calidad
    //echo debugQuery($pdo, $sqlP, $params);
    $qP->execute($params);
    $dataP = $qP->fetch(PDO::FETCH_ASSOC);
    
    if ($dataP['cant'] == 0) {
      
      $sql = "INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto, id_material, posicion, cantidad, largo, ancho, marca, peso, peso_calculado_posicion, finalizado, id_colada, diametro, calidad) VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_lista_corte_conjunto,$id_material,$nombre_posicion,$cantidad_posicion,$largo,$ancho,$marca,$peso,$peso_calculado_posicion,$idColada,$diametro,$calidad]);
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

      $sql = "UPDATE listas_corte_conjuntos set peso = peso + $peso_calculado_posicion where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_lista_corte_conjunto]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      $idComputoDetalle = 0;
      //$sql = "SELECT cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte_revisiones lcr on lcr.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lcr.id where cd.cancelado = 0 and lcc.id = ? and m.id = ?";
      $sql = "SELECT cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where cd.cancelado = 0 and lcc.id = ? and m.id = ?";
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

          header("Location: nuevaListaCorteConjuntos.php?modo=update&id_lista_corte=".$data["id_lista_corte"]);
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
$sql = "SELECT lcc.nombre, lcc.id_lista_corte, lc.id_estado_lista_corte, lc.id AS id_lista_corte_revision, lc.id_proyecto, lc.numero FROM listas_corte_conjuntos lcc INNER JOIN listas_corte lc ON lcc.id_lista_corte=lc.id WHERE lcc.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte_conjunto]);
$data = $q->fetch(PDO::FETCH_ASSOC);

$descripcionProyecto = getDescripcionProyecto($pdo, $data["id_proyecto"]);

Database::disconnect();?>

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
          include_once("head_page.php");?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Posiciones para el Conjunto <?=$data['nombre']?> LC #<?=$data['numero'].$descripcionProyecto?>
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
                                
                                $sql = " SELECT pos.id, pos.posicion, pos.cantidad, m.concepto, pos.id_material, pos.ancho, pos.largo, pos.diametro, pos.marca, pos.peso_calculado_posicion, GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos, GROUP_CONCAT(tp.id SEPARATOR ',') AS id_procesos, pos.finalizado FROM lista_corte_posiciones pos inner join materiales m on m.id = pos.id_material LEFT JOIN lista_corte_procesos lcp ON lcp.id_lista_corte_posicion=pos.id LEFT JOIN tipos_procesos tp ON lcp.id_tipo_proceso=tp.id WHERE pos.id_lista_corte_conjunto = ".$id_lista_corte_conjunto." GROUP BY pos.id ";
                                foreach ($pdo->query($sql) as $row) {
                                  $pesoPosicion = $row["peso_calculado_posicion"];
                                  /*if (str_starts_with($row["concepto"], "Chapa")) {
                                    if (empty($row["largo"]) && empty($row["ancho"])) {
                                      $pesoPosicion = $row["peso"]*$row["diametro"]*$row["diametro"];
                                    } else {
                                      $pesoPosicion = $row["peso"]*$row["largo"]*$row["ancho"]/1000;	
                                    }
                                  }
                                  if (str_starts_with($row["concepto"], "Perfil")) {
                                    $pesoPosicion = $row["peso"]*$row["largo"]/1000000;
                                  }*/
                                  //hacer la logica y sumar el peso
                                  $pesoTotal += $pesoPosicion;?>
                                  <tr>
                                    <td class="d-none"><?=$row["id"]?></td>
                                    <td><?=$row["posicion"]?></td>
                                    <td><?=$row["cantidad"]?></td>
                                    <td data-id="<?=$row["id_material"]?>"><?=$row["concepto"]?></td>
                                    <td><?=$row["ancho"]?></td>
                                    <td><?=$row["largo"]?></td>
                                    <td><?=$row["diametro"]?></td>
                                    <td><?=$row["marca"]?></td>
                                    <td><?=$pesoPosicion?></td>
                                    <td data-id="<?=$row["id_procesos"]?>"><?=$row["procesos"]?></td>
                                  </tr><?php
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
							              <b>PESO TOTAL CONJUNTO:&nbsp;<?php echo number_format($pesoTotal,1,",",".");?>&nbsp;kgs</b>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="form-group col-3">
                          <label>Posicion(*)</label>
                          <input name="nombre_posicion" type="text" maxlength="99" autofocus class="form-control nombre_posicion" required="required" value=""><?php
                          if (!empty($_GET['error_repetido'])) {
                            echo "<font color='red'><b>El nombre de Posición utilizado ya está en uso</b></font>";
                          }?>
						            </div>
                        <div class="form-group col-3">
                          <label>Cantidad(*)</label>
                          <input name="cantidad_posicion" type="number" step="0.01" min="0.01" maxlength="99" class="form-control cantidad_posicion" required="required" value="">
                        </div>
                        <div class="form-group col-6">
                          <label>Concepto(*)</label><br>
                          <select name="id_material" class="js-example-basic-single id_material" onchange="jsCompletarPeso(this.value);">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            //$sqlZon = "SELECT distinct m.id, m.codigo, m.concepto, c.nro_computo, max(c.nro_revision) from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where lcc.id = ".$id_lista_corte_conjunto." GROUP BY m.id, m.codigo, m.concepto, c.nro_computo ";
							              //$sqlZon = "SELECT `id`, `concepto`, `codigo` FROM `materiales` WHERE anulado = 0 "; //que traiga los conceptos 
                            $sqlZon = "SELECT cd.id AS id_computo_detalle, c.id AS id_computo, m.id AS id_material, m.codigo, m.concepto FROM listas_corte_conjuntos lcc JOIN listas_corte lc ON lcc.id_lista_corte=lc.id JOIN proyectos p ON lc.id_proyecto=p.id JOIN tareas t ON t.id_proyecto=p.id JOIN computos c ON c.id_tarea=t.id JOIN computos_detalle cd ON cd.id_computo=c.id JOIN materiales m ON cd.id_material=m.id WHERE lcc.id=$id_lista_corte_conjunto AND c.id_estado IN (3,4,5) ORDER BY m.concepto";
							              $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                              <option value='<?=$fila['id_material']?>'><?=$fila['concepto']." (".$fila['codigo'].") - Computo ".$fila['id_computo']?></option><?php
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="row">
                        <div class="form-group col-2">
                          <label>Ancho (En mm)</label>
                          <input name="ancho" type="number" step="0.01" maxlength="99" class="form-control ancho" value="">
                        </div>
                        <div class="form-group col-2">
                          <label>Largo (En mm)</label>
                          <input name="largo" type="number" step="0.01" maxlength="99" class="form-control largo" value="">
                        </div>
                        <div class="form-group col-2">
                          <label>Diametro (En mm)</label>
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
                        <div class="form-group col-2">
                          <label>Peso estimado calculado</label><br>
                          <span id="pesoCalculado" style="font-weight: bold;font-size: x-large;">0 kg</span>
                          <input type="hidden" name="hiddenPesoCalculado" id="hiddenPesoCalculado" value="0">
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
                        <a href='nuevaListaCorteConjuntos.php?modo=update&id_lista_corte=<?=$data["id_lista_corte"]?>' class="btn btn-light">Volver</a>
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
	      
        var table=$('#dataTables-example667').DataTable({
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

        $("input[name='largo'], input[name='ancho'], input[name='peso']").on("input change", calcularPesoMaterial);
        $("select[name='id_material']").on("input change", calcularPesoMaterial);

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
      });

      function calcularPesoMaterial() {
        const tipoMaterial = $("select[name='id_material'] option:selected").text().trim(); // "Chapa", "Perfil", "Caño", etc.
        const largoRaw = $("input[name='largo']").val();
        const anchoRaw = $("input[name='ancho']").val();
        const pesoRaw  = $("input[name='peso']").val();

        // Permite coma decimal y convierte a número
        const largo = parseFloat(String(largoRaw).replace(',', '.'));   // mm
        const ancho = parseFloat(String(anchoRaw).replace(',', '.'));   // mm
        const peso  = parseFloat(String(pesoRaw ).replace(',', '.'));   // kg/m (lineal) o kg/m² (chapa)

        // Helper para redondear a 2 decimales sin problemas de flotantes
        const r2 = (n) => Math.round(n * 100) / 100;

        let pesoCalculado = 0;

        // Validaciones mínimas
        if (!isFinite(largo) || !isFinite(peso)) {
          pesoCalculado = 0;
        } else if (tipoMaterial.startsWith("Chapa")) {
          // Chapa: cálculo por área = (largo * ancho) en m²
          if (!isFinite(ancho)) {
            pesoCalculado = 0; // falta ancho para chapa
          } else {
            const area_m2 = (largo / 1000) * (ancho / 1000);
            pesoCalculado = r2(area_m2 * peso); // kg (peso es kg/m²)
          }
        } else if (tipoMaterial.startsWith("Perfil") || tipoMaterial.startsWith("Caño")) {
          // Perfil / Caño: cálculo lineal; se IGNORA el ancho
          const largo_m = largo / 1000;
          pesoCalculado = r2(largo_m * peso); // kg (peso es kg/m)
        } else {
          // Otros tipos: por defecto, lineal (si querés otro comportamiento, ajustá acá)
          const largo_m = largo / 1000;
          pesoCalculado = r2(largo_m * peso);
        }

        // Actualiza UI / hidden
        $("#pesoCalculado").text(`${pesoCalculado.toFixed(2)} kg`);
        $("#hiddenPesoCalculado").val(pesoCalculado); // valor numérico (con . decimal)
      }


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
            //$("#pesokg").html(resp);
            $("input[name='peso']").val(resp);
            calcularPesoMaterial();
          }
        });
	    }
      
    </script>
  </body>
</html>