<?php
if (!empty($_POST)) {

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
      header("Location: listarCompras.php");
  }
  
  $pdo = Database::connect();
  $sql_estado = "SELECT id_estado_compra FROM compras WHERE id = ?";
  $q_estado = $pdo->prepare($sql_estado);
  $q_estado->execute([$id]);
  $estado_actual = $q_estado->fetch(PDO::FETCH_ASSOC);
  Database::disconnect();
  
  if (!$estado_actual || $estado_actual['id_estado_compra'] != 1) {
    header("Location: listarCompras.php");
    exit();
  }

  $pdo_calc = Database::connect();
  $sql_calc = "SELECT SUM(precio * cantidad) AS subtotal FROM compras_detalle WHERE id_compra = ?";
  $q_calc = $pdo_calc->prepare($sql_calc);
  $q_calc->execute([$_GET['id']]);
  $calc_data = $q_calc->fetch(PDO::FETCH_ASSOC);
  $subtotal = $calc_data['subtotal'] ?? 0;

  $porcentaje_iva = 0;
  if ($_POST['id_tipo_iva'] == 2) {
      $porcentaje_iva = 0.105;
  } elseif ($_POST['id_tipo_iva'] == 3) {
      $porcentaje_iva = 0.21;
  }
  
  $iva = $subtotal * $porcentaje_iva;
  $descuento = floatval($_POST['descuento'] ?? 0);
  $total = $subtotal + $iva - $descuento;
  Database::disconnect();
  
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
  $sql = "UPDATE compras SET id_cuenta_proveedor = ?, fecha_emision = ?, fecha_entrega = ?, id_moneda = ?, tipo_cambio_dia = ?, descuento = ?, id_forma_pago = ?, comentarios = ?, total = ?, iva = ?, id_tipo_iva = ? WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([
      $_POST['id_cuenta_proveedor'],
      $_POST['fecha_emision'], 
      $_POST['fecha_entrega'],
      $_POST['id_moneda'],
      $_POST['tipo_cambio_dia'],
      $descuento,
      $_POST['id_forma_pago'],
      $_POST['comentarios'],
      $total,
      $iva,
      $_POST['id_tipo_iva'],
      $_GET['id']
  ]);
    
  $sql = "INSERT INTO logs(`fecha_hora`, `id_usuario`, `detalle_accion`,`modulo`,link) VALUES (now(),?,'Modificación de orden de compra','Compras','verCompra.php?id=$id')";
  $q = $pdo->prepare($sql);
  $q->execute(array($_SESSION['user']['id']));

  Database::disconnect();

  header("Location: listarCompras.php");
} else {

    $id_compra = $_GET['id'];
    
    $_GET['id'] = isset($_GET['id_pedido']) ? $_GET['id_pedido'] : null;
        
    include 'gestionarPedido.php';
}
    
?>