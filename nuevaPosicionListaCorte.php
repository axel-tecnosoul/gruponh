<?php
    require("config.php");
    if (empty($_SESSION['user'])) {
        header("Location: index.php");
        die("Redirecting to index.php");
    }
    
    require 'database.php';
    
    if (!empty($_POST)) {
        
        // insert data
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$idColada = null;
		$sql = "SELECT col.id FROM `coladas` col inner join compras com on com.id = col.id_compra inner join pedidos p on p.id = com.id_pedido inner join computos c on c.id = p.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pr on pr.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = pr.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id WHERE col.id_material = ? and lcc.id = ? ";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['id_material'],$_GET['id']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        if (!empty($data['id'])) {
			$idColada = $data['id'];	
		}
		
		$calidad = "";
		if (!empty($_POST['id_material'])) {
			$sqlM = " select calidad from materiales where id = ?";
			$qM = $pdo->prepare($sqlM);
			$qM->execute([$_POST['id_material']]);
			$dataM = $qM->fetch(PDO::FETCH_ASSOC);
			$calidad = $dataM['calidad'];
		}
		
        $sql = "INSERT INTO `lista_corte_posiciones`(`id_lista_corte_conjunto`, `id_material`, `posicion`, `cantidad`, `largo`, `ancho`, `marca`, `peso`, `finalizado`, `id_colada`, `diametro`, `calidad`) VALUES (?,?,?,?,?,?,?,?,0,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$_GET['id'],$_POST['id_material'],$_POST['posicion'],$_POST['cantidad'],$_POST['largo'],$_POST['ancho'],$_POST['marca'],$_POST['peso'],$idColada,$_POST['diametro'],$calidad]);
        $id_posicion = $pdo->lastInsertId();
        
		$idComputoDetalle = 0;
		$sql = "select cd.id idComputoDetalle from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where cd.cancelado = 0 and lcc.id = ? and m.id = ?";
		$q = $pdo->prepare($sql);
        $q->execute([$_GET['id'],$_POST['id_material']]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
		$idComputoDetalle = $data['idComputoDetalle'];
		
		$sql = "update `computos_detalle` set comprado = comprado + ?, reservado = reservado - ?  where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['cantidad'],$_POST['cantidad'],$idComputoDetalle]);
		
		$sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Nueva Posición ID #$id_posicion de Concepto en Conjunto de Lista de Corte','Listas de Corte','')";
		$q = $pdo->prepare($sql);
		$q->execute(array($_SESSION['user']['id']));
		
        Database::disconnect();
        header("Location: listarListasCorte.php");
    }
    
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
  <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
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
          $ubicacion="Nueva Posición en Conjunto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
					<form class="form theme-form" role="form" method="post" action="nuevaPosicionListaCorte.php?id=<?php echo $_GET['id']?>">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
						<div class="form-group row">
						<label class="col-sm-3 col-form-label">Concepto(*)</label>
						<div class="col-sm-9">
						<select name="id_material" id="id_material" class="js-example-basic-single col-sm-12" required="required" autofocus>
						<option value="">Seleccione...</option>
						<?php
						$pdo = Database::connect();
						$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$sqlZon = "select m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte lc on lc.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lc.id where cd.cancelado = 0 and lcc.id = ".$_GET['id'];
						$q = $pdo->prepare($sqlZon);
						$q->execute();
						while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
							if ($fila['reservado'] > 0) {
								echo "<option value='".$fila['id']."'";
								echo ">".$fila['concepto']." (".$fila['codigo'].") - Reservado: ".$fila['reservado']."</option>";	
							}							
						}
						Database::disconnect();
						?>
						</select>
						</div>
						</div>
						<div class="form-group row">
							<label class="col-sm-3 col-form-label">Posición(*)</label>
							<div class="col-sm-9"><input name="posicion" type="text" maxlength="99" class="form-control" required="required"></div>
						  </div>
						<div class="form-group row">
							<label class="col-sm-3 col-form-label">Cantidad(*)</label>
							<div class="col-sm-9"><input name="cantidad" type="number" step="0.01" class="form-control" required="required"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Largo</label>
							<div class="col-sm-9"><input name="largo" type="number" step="0.01" class="form-control"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Ancho</label>
							<div class="col-sm-9"><input name="ancho" type="number" step="0.01" class="form-control"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Diámetro</label>
							<div class="col-sm-9"><input name="diametro" type="number" step="0.01" class="form-control"></div>
						  </div>
						  <div class="form-group row">
							<label class="col-sm-3 col-form-label">Peso kg</label>
							<div class="col-sm-9"><input name="peso" type="number" step="0.01" class="form-control"></div>
						  </div>
							<div class="form-group row">
							<label class="col-sm-3 col-form-label">Marca</label>
							<div class="col-sm-9"><input name="marca" type="text" maxlength="99" class="form-control"></div>
						  </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
						<button class="btn btn-primary" type="submit">Crear</button>
						<a href="listarListasCorte.php" class="btn btn-light">Volver</a>
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
  </body>
</html>