<?php
include 'database.php';
$aProductos=[];
$total=0;
$recordsFiltered=0;
$debug="";
$queryInfo="";
if(!empty($_GET["almacen"]) and $_GET["almacen"]>0) {

  $id_almacen=$_GET["almacen"];

  $pdo = Database::connect();
  $columns = $_GET['columns'];
  //var_dump($columns);

  $data_columns = ["CONCAT(pr.nombre,' ',pr.apellido)","p.codigo","c.categoria","p.descripcion","s.cantidad","p.precio"];

  $fields = ["s.id","p.cb","p.codigo","c.categoria","p.descripcion","p.precio","s.cantidad","s.id_modalidad", "p.id_proveedor", "s.id_producto","pr.nombre","pr.apellido"];

  $from="FROM stock s INNER JOIN productos p ON p.id = s.id_producto INNER JOIN categorias c ON c.id = p.id_categoria INNER JOIN clientes pr ON p.id_proveedor = pr.id";

  $orderBy = " ORDER BY ";
  foreach ($_GET['order'] as $order) {
    //var_dump($order);
    //$orderBy .= $order['column'] + 1 . " {$order['dir']}, ";
    $orderBy .= $data_columns[$order['column']] . " {$order['dir']}, ";
    //var_dump($orderBy);
  }

  $orderBy = substr($orderBy, 0, -2);

  $where = " s.id_almacen = $id_almacen AND p.activo=1 AND ";
  foreach ($columns as $k => $column) {
    if ($search = $column['search']['value']) {
      $search=trim($search,"$");
      $search=trim($search,"^");
      $where .= $fields[$k].' LIKE "'.$search.'" AND ';
    }
  }

  $globalSearch = $_GET['search'];
  if ( $globalSearchValue = $globalSearch['value'] ) {
    $aWhere=[];
    foreach ($fields as $k => $field) {
      $aWhere[]=$field.' LIKE "%'.$globalSearchValue.'%"';
    }
    $where .= '('.implode(' OR ', $aWhere).')';
  }

  if(substr($where, -5)==" AND ") $where = substr($where, 0, -5);

  $length = $_GET['length'];
  $start = $_GET['start'];

  //OBTENEMOS EL TOTAL DE REGISTROS
  $countSql = "SELECT count($fields[0]) as Total $from";
  $countSt = $pdo->query($countSql);
  $total = $countSt->fetch()['Total'];


  //OBTENEMOS EL TOTAL DE REGISTROS CON FILTRO APLICADO
  $queryFiltered="SELECT COUNT($fields[0]) AS recordsFiltered $from ".($where ? "WHERE $where " : '');
  //var_dump($queryFiltered);
  $resFilterLength = $pdo->query($queryFiltered);
  $recordsFiltered = $resFilterLength->fetch()['recordsFiltered'];

  $campos=implode(",", $fields);

  $sql = "SELECT $campos $from ".($where ? "WHERE $where " : '')."$orderBy LIMIT $length OFFSET $start";
  error_log($sql);
  //var_dump($sql);
  $debug.=$sql;
  $st = $pdo->query($sql);

  if ($st) {
    //$rs = $st->fetchAll(PDO::FETCH_FUNC, fn($id, $name, $price) => [$id, $name, $price] );
    foreach ($pdo->query($sql) as $row) {
      //var_dump($row);
      
      //$fields = ["p.cb","p.codigo","c.categoria","p.descripcion","p.precio","s.cantidad","s.id"];
      $aProductos[]=[
        "id_stock"=>$row["id"],
        "codigo"=>$row["codigo"],
        "categoria"=>$row["categoria"],
        "descripcion"=>$row["descripcion"],
        "precio"=>$row["precio"],
        "cantidad"=>$row["cantidad"],
        "cb"=>$row["cb"],
        "id_modalidad"=>$row["id_modalidad"],
        "id_proveedor"=>$row["id_proveedor"],
        "proveedor"=>$row["nombre"]." ".$row["apellido"],
        "id_producto"=>$row["id_producto"],
      ];
    }

    $queryInfo=[
      'campos' => $campos,
      'from' => $from,
      'where' => $where,
      'orderBy' => $orderBy,
      'length' => $length,
      'start' => $start,
      'query' => $sql,
    ];

  } else {
    var_dump($pdo->errorInfo());
    die;
  }
  Database::disconnect();
}


echo json_encode([
  'data' => $aProductos,
  'recordsTotal' => $total,
  'recordsFiltered' => $recordsFiltered,//count($aProductos),
  'debug'=>$debug,
  'queryInfo'=>$queryInfo,
]);