<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
require_once 'funciones.php';

// Modo debug - definir aquí para habilitar/deshabilitar fácilmente
$debug = false;

// Asignar variables probando GET primero, luego POST como fallback
$entidad_tipo = $_GET['entidad_tipo'] ?? $_POST['entidad_tipo'] ?? 'proyectos';
$entidad_id = $_GET['entidad_id'] ?? $_GET['id'] ?? $_POST['entidad_id'] ?? null;

if ($debug) {
  echo "<pre>DEBUG - Variables asignadas:\n";
  echo "entidad_tipo: " . var_export($entidad_tipo, true) . "\n";
  echo "entidad_id: " . var_export($entidad_id, true) . "\n";
  echo "Método: " . (empty($_POST) ? 'GET' : 'POST') . "\n";
  echo "</pre>";
}

// Validar que tenemos los parámetros necesarios
if (empty($entidad_id)) {
  // Si no tenemos entidad_id y venimos de listarSucesos, redirigir ahí
  $referrer = $_SERVER['HTTP_REFERER'] ?? '';
  if (strpos($referrer, 'listarSucesos.php') !== false) {
    header("Location: listarSucesos.php");
    exit();
  }
  
  // Si no sabemos de dónde proviene, redirigir a listarSucesos
  if (empty($referrer) || (!strpos($referrer, 'listarPedidos.php') && !strpos($referrer, 'listarCompras.php') && !strpos($referrer, 'listarProyectos.php'))) {
    header("Location: listarSucesos.php");
    exit();
  }
  
  switch($entidad_tipo) {
    case 'pedidos':
      header("Location: listarPedidos.php");
      break;
    case 'compras':
      header("Location: listarCompras.php");
      break;
    default:
      header("Location: listarProyectos.php");
  }
  exit();
}

// Validar que el tipo de entidad sea permitido
$entidades_permitidas = ['proyectos', 'pedidos', 'compras'];
if (!in_array($entidad_tipo, $entidades_permitidas)) {
  header("Location: listarProyectos.php");
  exit();
}

if (!empty($_POST)) {
  if ($debug) {
    echo "<pre>DEBUG - Datos POST:\n";
    print_r($_POST);
    echo "</pre>";
  }
  
  require_once("PHPMailer/class.phpmailer.php");
  require_once("PHPMailer/class.smtp.php");

  if ($debug) {
    echo "<pre>DEBUG - Procesando POST:\n";
    echo "entidad_tipo: " . var_export($entidad_tipo, true) . "\n";
    echo "entidad_id: " . var_export($entidad_id, true) . "\n";
    echo "</pre>";
  }
  
  // Validar que tenemos los datos necesarios (deberían estar ya asignados correctamente)
  if (empty($entidad_tipo) || empty($entidad_id)) {
    $error = "Error: Tipo de entidad e ID son requeridos.";
    if ($debug) {
      echo "<pre>DEBUG - Error en validación: $error</pre>";
    }
  } else {
    try {
      $pdo = Database::connect();
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      
      if ($debug) {
        echo "<pre>DEBUG - Insertando suceso...</pre>";
      }
      
      $sql = "INSERT INTO sucesos (entidad_tipo, entidad_id, fecha_hora, suceso, id_tipo_suceso, titulo, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)";
      $q = $pdo->prepare($sql);
      $result = $q->execute([$entidad_tipo, $entidad_id, $_POST['fecha_hora'], $_POST['suceso'], $_POST['id_tipo_suceso'], $_POST['titulo'], $_SESSION['user']['id']]);
      
      if ($debug) {
        echo "<pre>DEBUG - Suceso insertado: " . var_export($result, true) . "</pre>";
      }
      
      $sql = "SELECT tipo FROM tipos_suceso WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST['id_tipo_suceso']]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $tipoSuceso = $data['tipo'] ?? 'Desconocido';
      
      if ($debug) {
        echo "<pre>DEBUG - Tipo suceso: " . var_export($tipoSuceso, true) . "</pre>";
      }
      
      // Determinar módulo y link de redirección según el tipo de entidad
      $modulo = ucfirst($entidad_tipo);
      $link_ver = '';
      switch($entidad_tipo) {
        case 'proyectos':
          $link_ver = "verProyecto.php?id=$entidad_id";
          break;
        case 'pedidos':
          $link_ver = "verPedido.php?id=$entidad_id";
          break;
        case 'compras':
          $link_ver = "verCompra.php?id=$entidad_id";
          break;
      }
      
      if ($debug) {
        echo "<pre>DEBUG - Insertando log...\nMódulo: $modulo\nLink: $link_ver</pre>";
      }
      
      $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,'Nuevo Suceso',?,?)";
      $q = $pdo->prepare($sql);
      $logResult = $q->execute([$_SESSION['user']['id'], $modulo, $link_ver]);
      
      if ($debug) {
        echo "<pre>DEBUG - Log insertado: " . var_export($logResult, true) . "</pre>";
      }
      
      // Crear notificación usando la función centralizada
      $idTipoNotificacion = 12;
      $idEntidad = (int)$entidad_id;
      $detalleNotificacion = "ID " . ucfirst($entidad_tipo) . ": #" . $entidad_id;
      $asuntoEmail = "Módulo $modulo - Nuevo Suceso";
      $cuerpoEmail = "Nuevo suceso de tipo '" . $tipoSuceso . "' dado de alta en " . ucfirst($entidad_tipo) . ": #" . $entidad_id;      if ($debug) {
        echo "<pre>DEBUG - Creando notificación...\nTipo: $idTipoNotificacion\nEntidad: $idEntidad\nDetalle: $detalleNotificacion</pre>";
      }
      
      crearNotificacion($pdo, $idTipoNotificacion, $idEntidad, $detalleNotificacion, $asuntoEmail, $cuerpoEmail);
      
      if ($debug) {
        echo "<pre>DEBUG - Notificación creada exitosamente</pre>";
      }
      
      Database::disconnect();
      
      if ($debug) {
        echo "<pre>DEBUG - Proceso completado exitosamente. Redirigiendo...</pre>";
        echo "<a href='listarProyectos.php'>Ir a listar proyectos</a>";
        exit();
      }
      
      // Determinar dónde redirigir según el referrer
      $referrer = $_SERVER['HTTP_REFERER'] ?? '';
      if (strpos($referrer, 'listarSucesos.php') !== false) {
        header("Location: listarSucesos.php");
        exit();
      }
      
      // Redirección según el tipo de entidad
      switch($entidad_tipo) {
        case 'pedidos':
          header("Location: listarPedidos.php");
          break;
        case 'compras':
          header("Location: listarCompras.php");
          break;
        default:
          header("Location: listarProyectos.php");
      }
      exit();
      
    } catch (Exception $e) {
      $error = "Error al procesar el suceso: " . $e->getMessage();
      if ($debug) {
        echo "<pre>DEBUG - Excepción capturada:\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
      }
      Database::disconnect();
    }
  }
} 

// Obtener datos para mostrar información de la entidad
if (!isset($error)) {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  if ($debug) {
    echo "<pre>DEBUG - Obteniendo información de entidad...\nTipo: $entidad_tipo\nID: $entidad_id</pre>";
  }
  
  // Obtener información básica de la entidad para mostrar contexto
  $entidad_info = '';
  switch($entidad_tipo) {
    case 'proyectos':
      /*$sql = "SELECT p.id, p.descripcion, s.nro_sitio, s.nro_subsitio FROM proyectos p LEFT JOIN sitios s ON s.id = p.id_sitio WHERE p.id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$entidad_id]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      if ($data) {*/

      $descProyecto = getDescripcionProyecto($pdo, $entidad_id);
      $entidad_info = "Proyecto # " . $descProyecto;
      //$entidad_info = ($data['nro_sitio'] ? $data['nro_sitio'] . '-' . $data['nro_subsitio'] . ': ' : '') . $data['descripcion'];
      //}
      break;
    case 'pedidos':
      $sql = "SELECT id FROM pedidos WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$entidad_id]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      if ($data) {
        $entidad_info = "Pedido #" . $entidad_id;
      }
      break;
    case 'compras':
      $sql = "SELECT id, id_pedido, nro_revision FROM compras WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$entidad_id]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      if ($data) {
        $entidad_info = "Compra #" . $entidad_id . " / ". $data['nro_revision'];// . " - " . $data['id_pedido'];
      }
      break;
  }
  
  // Si no encontramos la entidad, redirigir
  if (empty($entidad_info)) {
    switch($entidad_tipo) {
      case 'pedidos':
        header("Location: listarPedidos.php");
        break;
      case 'compras':
        header("Location: listarCompras.php");
        break;
      default:
        header("Location: listarProyectos.php");
    }
    exit();
  }
  
  Database::disconnect();
}

    $fecha_hora_actual = date('Y-m-d\TH:i');
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
          $ubicacion = "Agregar Suceso";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?> - <?=isset($entidad_info) ? $entidad_info : ""?></h5><?php
                    if (isset($error)) {?>
                      <div class="alert alert-danger mt-2"><?=$error?></div><?php
                    }
                    if ($debug) {?>
                      <div class="alert alert-info mt-2">
                        <strong>MODO DEBUG ACTIVO</strong><br>
                        Entidad Tipo: <?=$entidad_tipo?><br>
                        Entidad ID: <?=$entidad_id?><br>
                        URL: <?=$_SERVER['REQUEST_URI']?>
                      </div><?php
                    }?>
                  </div>
				          <form class="form theme-form" role="form" method="post" action="nuevoSuceso.php">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <!-- Campos hidden para tipo de entidad e ID -->
                          <input type="hidden" name="entidad_tipo" value="<?=$entidad_tipo?>">
                          <input type="hidden" name="entidad_id" value="<?=$entidad_id?>">
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Fecha Hora(*)</label>
                            <div class="col-sm-9">
                              <input type="datetime-local" name="fecha_hora" value="<?=$fecha_hora_actual?>" onfocus="this.showPicker()" class="form-control" required="required" autofocus>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Tipo Suceso(*)</label>
                            <div class="col-sm-9">
                              <select name="id_tipo_suceso" id="id_tipo_suceso" class="js-example-basic-single col-sm-12" required="required">
                                <option value="">Seleccione...</option><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $sqlZon = "SELECT id, tipo FROM tipos_suceso WHERE 1";
                                $q = $pdo->prepare($sqlZon);
                                $q->execute();
                                while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {?>
                                  <option value='<?=$fila['id'] ?>'><?=$fila['tipo'] ?></option><?php
                                }
                                Database::disconnect();?>
                              </select>
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Título(*)</label>
                            <div class="col-sm-9">
                              <input type="text" name="titulo" class="form-control" required="required">
                            </div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Suceso(*)</label>
                            <div class="col-sm-9">
                              <textarea name="suceso" class="form-control" required="required"></textarea>
                            </div>
                          </div>	
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-sm-9 offset-sm-3">
                        <button class="btn btn-primary" type="submit">Agregar</button><?php
                        // Determinar dónde volver según el referrer
                        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
                        if (strpos($referrer, 'listarSucesos.php') !== false) {
                          echo "<a href='listarSucesos.php' class='btn btn-light'>Volver</a>";
                        } else {
                          switch($entidad_tipo) {
                            case 'pedidos':
                              echo "<a href='listarPedidos.php' class='btn btn-light'>Volver</a>";
                              break;
                            case 'compras':
                              echo "<a href='listarCompras.php' class='btn btn-light'>Volver</a>";
                              break;
                            default:
                              echo "<a href='listarProyectos.php' class='btn btn-light'>Volver</a>";
                          }
                        }?>
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