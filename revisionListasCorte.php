<?php
require("config.php");
require("PHPMailer/class.phpmailer.php");
require("PHPMailer/class.smtp.php");

if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';
if (!empty($_POST)) {
    
  // insert data
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $modoDebug=0;

  if($modoDebug==1){
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_GET);
  }

  $nuevo_nro_revision=$_POST["revision"];
  $id_lista_corte_revision=$_GET["id_lista_corte_revision"];

  $sql = "SELECT id_lista_corte,nro_revision FROM listas_corte_revisiones WHERE id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte_revision]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  if ($modoDebug==1) {
    echo $data["nro_revision"]."==".$nuevo_nro_revision."<br>";
    echo "data[nro_revision]==nuevo_nro_revision<br>";
  }

  if($data["nro_revision"]==$nuevo_nro_revision){
    //modificamos los datos de la revision
    if ($modoDebug==1) {
      echo "MODIFICAMOS LA REVISION<br><br>";
    }

    $redirect="revisionListasCorte.php?id_lista_corte=".$data["id_lista_corte"]."&nro_revision=".$nuevo_nro_revision;
	
	$sql2 = "SELECT id FROM `cuentas` WHERE id_usuario = ? ";
	$q2 = $pdo->prepare($sql2);
	$q2->execute([$_SESSION['user']['id']]);
	$data2 = $q2->fetch(PDO::FETCH_ASSOC);
	if (!empty($data2)) {
		$idCuentaRealizo = $data2['id'];
	}

    $sql = "UPDATE listas_corte_revisiones SET fecha = ?, descripcion = ?, id_cuenta_realizo = ?, id_cuenta_reviso = ?, id_cuenta_valido = ? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['fecha'],$_POST['descripcion'],$idCuentaRealizo,$_POST['id_cuenta_reviso'],$_POST['id_cuenta_valido'],$id_lista_corte_revision]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
    
    if($_POST["idConjunto"]>0){
      
      $id_lista_corte_conjunto=$_POST['idConjunto'];
      //modificamos el conjunto
      $sql = "UPDATE listas_corte_conjuntos SET nombre = ?, cantidad = ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST['nombre'],$_POST['cantidad'],$id_lista_corte_conjunto]);

    }elseif($_POST["nombre"]!="" and $_POST["cantidad"]!=""){
      //creamos una nueva
      //INSERTAMOS LOS CONJUNTOS DE LAS LISTA DE CORTE
      $sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$data["id_lista_corte"],$_POST["nombre"],$_POST["cantidad"],0,1]);
      $id_lista_corte_conjunto = $pdo->lastInsertId();

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nuevo Conjunto en Revision de Lista de Corte','Listas de Corte')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));
    }

    if($_POST["id_posicion"]>0){
      //modificamos la posicion
      
      $id_lista_corte_posicion=$_POST['id_posicion'];

      $sql = "UPDATE listas_corte_conjuntos set peso = peso - (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$_POST['cantidad_posicion'],$_POST['id_material'],$id_lista_corte_conjunto]);

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

    }elseif($_POST["nombre_posicion"]!="" and $_POST["cantidad_posicion"]!=""){
      //INSERTAMOS LA POSICION DEL CONJUNTO
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
        
      $sql = "INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto, id_material, posicion, cantidad, largo, ancho, marca, peso, finalizado, id_colada, diametro, calidad) VALUES (?,?,?,?,?,?,?,?,0,?,?,'')";
      $q = $pdo->prepare($sql);
      $q->execute([$id_lista_corte_conjunto,$_POST['id_material'],$_POST['nombre_posicion'],$_POST['cantidad_posicion'],$_POST['largo'],$_POST['ancho'],$_POST['marca'],$_POST['peso'],$idColada,$_POST['diametro']]);
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

      $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nueva Posicion en Revision de Lista de Corte','Listas de Corte')";
      $q = $pdo->prepare($sql);
      $q->execute(array($_SESSION['user']['id']));

    }

  }else{

    $redirect="revisionListasCorte.php?id_lista_corte=".$data["id_lista_corte"]."&nro_revision=".$nuevo_nro_revision;

    //creamos una nueva revision
    if ($modoDebug==1) {
      echo "CREAMOS UNA NUEVA REVISION<br><br>";
    }
	
	

    $id_lista_corte=$data["id_lista_corte"];
    $ultimo_nro_revision=$data["nro_revision"];

    $sql = "UPDATE listas_corte set ultimo_nro_revision = ? where id = ? and ultimo_nro_revision = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$nuevo_nro_revision,$id_lista_corte,$ultimo_nro_revision]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "SELECT lcr.id, lcr.id_lista_corte, lcr.id_proyecto, lcr.fecha, lcr.id_usuario, lcr.id_estado_lista_corte, lcr.anulado, lcr.nombre, lcr.numero, lcr.adjunto, lcr.id_cuenta_realizo, lcr.id_cuenta_reviso, lcr.id_cuenta_valido FROM listas_corte_revisiones lcr WHERE lcr.id_lista_corte = ? AND lcr.nro_revision = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte,$ultimo_nro_revision]);
    $data = $q->fetch(PDO::FETCH_ASSOC);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
	
	$sql = "update listas_corte_revisiones set id_estado_lista_corte = 7 where id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_GET["id_lista_corte_revision"]]);

    $sql = "INSERT INTO listas_corte_revisiones (id_lista_corte, id_proyecto, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, descripcion, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$data["id_lista_corte"],$data['id_proyecto'],$_POST['fecha'],$data['id_usuario'],2,$nuevo_nro_revision,$data['anulado'],$data['nombre'],$data['numero'],$_POST['descripcion'],$idCuentaRealizo,$_POST['id_cuenta_reviso'],$_POST['id_cuenta_valido']]);
    $id_lista_corte_revision = $pdo->lastInsertId();
	
	$idProyecto = $data['id_proyecto'];

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
	
    //OBTENEMOS LOS CONJUNTOS DEL LISTA DE CORTE
    $sql = "SELECT lcc.id,lcc.id_lista_corte,lcc.nombre,lcc.cantidad,lcc.peso,lcc.id_estado_lista_corte_conjuntos FROM listas_corte_conjuntos lcc WHERE lcc.id_lista_corte = ".$data['id'];
    foreach ($pdo->query($sql) as $row) {
      
      $nombreConjunto=$row['nombre'];
      $cantidadConjunto=$row['cantidad'];

      if ($modoDebug==1) {
        echo "nombreConjunto: $nombreConjunto<br>";
        echo "cantidadConjunto: $cantidadConjunto<br><br>";
      }

      if($_POST["idConjunto"]==$row['id']){
        //modificamos el conjunto
        $nombreConjunto=$_POST['nombre'];
        $cantidadConjunto=$_POST['cantidad'];

        if ($modoDebug==1) {
          echo "idConjunto ENCONTRADO, MODIFICAMOS LOS DATOS<br>";
          echo "nombreConjunto: $nombreConjunto<br>";
          echo "cantidadConjunto: $cantidadConjunto<br><br>";
        }
      }

      //INSERTAMOS LOS CONJUNTOS DE LAS LISTA DE CORTE
      $sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,?)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_lista_corte_revision,$nombreConjunto,$cantidadConjunto,$row['peso'],$row['id_estado_lista_corte_conjuntos']]);
      $id_lista_corte_conjunto = $pdo->lastInsertId();

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }

      //OBTENEMOS LAS POSICIONES DEL CONJUNTO
      $sql = "SELECT lcp.id,lcp.id_lista_corte_conjunto,lcp.id_material,lcp.posicion,lcp.cantidad,lcp.largo,lcp.ancho,lcp.marca,lcp.peso,lcp.finalizado,lcp.id_colada,lcp.diametro,lcp.calidad FROM lista_corte_posiciones lcp WHERE lcp.id_lista_corte_conjunto = ".$row["id"];
      foreach ($pdo->query($sql) as $row) {

        $id_material=$row["id_material"];
        $posicion=$row["posicion"];
        $cantidad=$row["cantidad"];
        $largo=$row["largo"];
        $ancho=$row["ancho"];
        $marca=$row["marca"];
        $peso=$row["peso"];
        $diametro=$row["diametro"];
        $finalizado=$row["finalizado"];
        $id_colada=$row["id_colada"];
        $calidad=$row["calidad"];

        if ($modoDebug==1) {
          echo "id_material: $id_material<br>";
          echo "posicion: $posicion<br>";
          echo "cantidad: $cantidad<br>";
          echo "largo: $largo<br>";
          echo "ancho: $ancho<br>";
          echo "marca: $marca<br>";
          echo "peso: $peso<br>";
          echo "diametro: $diametro<br><br>";
        }

        $cargarProcesosNuevos=0;

        if($_POST["id_posicion"]==$row['id']){
          //modificamos el conjunto
          $id_material=$_POST["id_material"];
          $posicion=$_POST["nombre_posicion"];
          $cantidad=$_POST["cantidad_posicion"];
          $largo=$_POST["largo"];
          $ancho=$_POST["ancho"];
          $marca=$_POST["marca"];
          $peso=$_POST["peso"];
          $diametro=$_POST["diametro"];

          $cargarProcesosNuevos=1;

          if ($modoDebug==1) {
            echo "id_posicion ENCONTRADO, MODIFICAMOS LOS DATOS<br>";
            echo "id_material: $id_material<br>";
            echo "posicion: $posicion<br>";
            echo "cantidad: $cantidad<br>";
            echo "largo: $largo<br>";
            echo "ancho: $ancho<br>";
            echo "marca: $marca<br>";
            echo "peso: $peso<br>";
            echo "diametro: $diametro<br><br>";
          }

        }

        //INSERTAMOS LAS POSICIONES DEL CONJUNTO
        $sql = "INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto,id_material,posicion,cantidad,largo,ancho,marca,peso,finalizado,id_colada,diametro,calidad) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $q = $pdo->prepare($sql);
        $q->execute([$id_lista_corte_conjunto,$id_material,$posicion,$cantidad,$largo,$ancho,$marca,$peso,$finalizado,$id_colada,$diametro,$calidad]);
        $id_lista_corte_posicion = $pdo->lastInsertId();

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }

        //ACTUALIZAMOS EL PESO DEL CONJUNTO
        $sql = "UPDATE listas_corte_conjuntos set peso = peso + (SELECT peso_metro * ? FROM materiales WHERE id = ?) where id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$_POST['cantidad_posicion'],$_POST['id_material'],$id_lista_corte_conjunto]);

        if ($modoDebug==1) {
          $q->debugDumpParams();
          echo "<br><br>Afe: ".$q->rowCount();
          echo "<br><br>";
        }

        if($cargarProcesosNuevos==1){
          //NO TOMAMOS EN CUENTA LOS PROCESOS DE LA REVISION ANTERIOR E INSERTAMOS LOS NUEVOS QUE VIENEN POR POST

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

        }else{
          //OBTENEMOS LOS PROCESOS DE LA POSICION
          $sql = "SELECT lcp.id_lista_corte_posicion,lcp.id_tipo_proceso,lcp.observaciones,lcp.id_estado_lista_corte_proceso FROM lista_corte_procesos lcp WHERE lcp.id_lista_corte_posicion = ".$row["id"];
          foreach ($pdo->query($sql) as $row) {
            //INSERTAMOS LOS PROCESOS DE LA POSICION
            $sql = "INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, observaciones, id_estado_lista_corte_proceso) VALUES (?,?,?,?)";
            $q = $pdo->prepare($sql);
            $q->execute([$id_lista_corte_posicion,$row['id_tipo_proceso'],$row['observaciones'],$row['id_estado_lista_corte_proceso']]);

            if ($modoDebug==1) {
              $q->debugDumpParams();
              echo "<br><br>Afe: ".$q->rowCount();
              echo "<br><br>";
            }
          }
        }
      }
    }

    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo) VALUES (now(),?,'Nueva Revision de Lista de Corte','Listas de Corte')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id']));
	
	$sql = "SELECT valor FROM `parametros` WHERE id = 1 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpHost = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 2 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpUsuario = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 3 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpClave = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 4 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpFrom = $data['valor'];  

	$sql = "SELECT valor FROM `parametros` WHERE id = 5 ";
	$q = $pdo->prepare($sql);
	$q->execute();
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$smtpFromName = $data['valor'];  
	
	$sql = "select s.nro_sitio, s.nro_subsitio, p.nro, p.nombre from proyectos p inner join sitios s on s.id = p.id_sitio where p.id = ? ";
	$q = $pdo->prepare($sql);
	$q->execute([$idProyecto]);
	$data = $q->fetch(PDO::FETCH_ASSOC);
	$descripcionProyecto = $data['nro_sitio']." - ".$data['nro_subsitio']." - ".$data['nro']." - ".$data['nombre'];
	
	$sql = " select t.id_usuario,u.email from usuarios_tipos_notificacion t inner join usuarios u on u.id = t.id_usuario where t.id_tipo_notificacion = 5 ";
	foreach ($pdo->query($sql) as $row) {
		
		$sql = "INSERT INTO `notificaciones`(`id_tipo_notificacion`, `id_usuario`, `fecha_hora`, `leida`,detalle,id_entidad) VALUES (5,?,now(),0,?,?)";
		$q = $pdo->prepare($sql);
		$q->execute([$row[0],'ID LC: #'.$id_lista_corte_revision,$id_lista_corte_revision]);
		
		$address = $row[1];
		
		$titulo = "ERP Notificaciones - Módulo Producción - Revisión Lista de Corte (".$descripcionProyecto.")";
		$mensaje="Revisión de Lista de Corte en el sistema para aprobar: #".$descripcionProyecto;
		
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

  }

  if ($modoDebug==1) {
    echo "redirect: ".$redirect;
    $pdo->rollBack();
    die();
  } else {
    Database::disconnect();
    header("Location: ".$redirect);
  }

}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT id AS id_lista_corte_revision, fecha, nombre, numero, id_estado_lista_corte, descripcion, nro_revision, id_cuenta_realizo, id_cuenta_reviso, id_cuenta_valido FROM listas_corte_revisiones WHERE ";

if(isset($_GET['id_lista_corte_revision'])){
  //nueva revision
  $sql.=" id = ? ";

  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id_lista_corte_revision']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $fecha=date("Y-m-d");
  $nro_revision=$data['nro_revision']+1;
  $descripcion="";
  $id_cuenta_realizo="";
  $id_cuenta_reviso="";
  $id_cuenta_valido="";

  $titleSubmit="Crear revision";
  $classSubmit="btn-success";

}elseif(isset($_GET['id_lista_corte']) and isset($_GET['nro_revision'])){
  //modificamos revision
  $sql.=" id_lista_corte = ? AND nro_revision = ?";

  $q = $pdo->prepare($sql);
  $q->execute([$_GET['id_lista_corte'],$_GET['nro_revision']]);
  $data = $q->fetch(PDO::FETCH_ASSOC);

  $fecha=$data["fecha"];
  $nro_revision=$_GET['nro_revision'];
  $descripcion=$data['descripcion'];
  $id_cuenta_realizo=$data["id_cuenta_realizo"];
  $id_cuenta_reviso=$data["id_cuenta_reviso"];
  $id_cuenta_valido=$data["id_cuenta_valido"];

  $titleSubmit="Modificar revision";
  $classSubmit="btn-primary";
}

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
          $ubicacion="Nuevo Conjunto";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <form class="form theme-form" role="form" method="post" action="revisionListasCorte.php?id_lista_corte_revision=<?=$data["id_lista_corte_revision"]?>">
                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Conjuntos de la Lista de Corte #<?=$data['numero']." - ".$data['nombre']?>
                        &nbsp;&nbsp;
                        <a href="#" id="link_ver_conjunto_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp;<?php
                        if (!empty(tienePermiso(330))) {?>
                          <img src="img/icon_baja.png" id="link_eliminar_conjunto" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;<?php
                        }?>
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="form-group row">
                            <div class="col-12">
                              <table class="display" id="tablaConjuntos">
                                <thead>
                                  <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Cantidad</th>
                                    <th>Peso kg</th>
                                    <th>Estado</th>
                                  </tr>
                                </thead>
                                <tfoot>
                                  <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Cantidad</th>
                                    <th>Peso kg</th>
                                    <th>Estado</th>
                                  </tr>
                                </tfoot>
                                <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = " SELECT c.id, c.nombre, c.cantidad, c.peso, e.estado FROM listas_corte_conjuntos c inner join estados_lista_corte_conjuntos e on e.id = c.id_estado_lista_corte_conjuntos WHERE c.id_lista_corte = ".$data["id_lista_corte_revision"];
                                  foreach ($pdo->query($sql) as $row) {
                                    echo '<tr>';
                                    echo '<td>'. $row["id"] . '</td>';
                                    echo '<td>'. $row["nombre"] . '</td>';
                                    echo '<td>'. $row["cantidad"] . '</td>';
                                    echo '<td>'. $row["peso"] . '</td>';
                                    echo '<td>'. $row["estado"] . '</td>';
                                    echo '</tr>';
                                  }
                                  Database::disconnect();?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                          <div class="form-group row">
                            <input type="hidden" name="idConjunto" id="idConjunto">
                            <label class="col-sm-3 col-form-label">Nombre(*)</label>
                            <div class="col-sm-9"><input name="nombre" type="text" maxlength="99" class="form-control"></div>
                          </div>
                          <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Cantidad(*)</label>
                            <div class="col-sm-9">
                              <input name="cantidad" data-original="" type="number" step="0.01" class="form-control">
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Posiciones para el Conjunto <span id="nombreConjunto"></span>
                        &nbsp;&nbsp;
                        <a href="#" id="link_ver_posicion_lc"><img src="img/eye.png" width="24" height="15" border="0" alt="Ver" title="Ver"></a>&nbsp;&nbsp;<?php
                        if (!empty(tienePermiso(330))) {?>
                          <img src="img/icon_baja.png" id="link_eliminar_posicion" style="cursor: pointer;" data-id="" width="24" height="25" border="0" alt="Eliminar" title="Eliminar">&nbsp;&nbsp;
                          <?php
                        }?>
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="form-group col-12">
                          <div class="dt-ext table-responsive">
                            <table class="display" id="tablaPosiciones">
                              <thead>
                                <tr>
                                  <th>ID</th>
                                  <th class="d-none">ID Conjunto</th>
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
                              <tfoot>
                                <tr>
                                  <th>ID</th>
                                  <th class="d-none">ID Conjunto</th>
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
                              </tfoot>
                              <tbody><?php
                                $pdo = Database::connect();
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                $sql="SELECT lcc.nombre,lcc.id,pos.id, pos.posicion, pos.cantidad, m.concepto, pos.id_material, pos.ancho, pos.largo, pos.diametro, pos.marca, pos.peso, GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos, GROUP_CONCAT(tp.id SEPARATOR ',') AS id_procesos, pos.finalizado,lcc.id AS id_conjunto FROM lista_corte_posiciones pos inner join materiales m on m.id = pos.id_material LEFT JOIN lista_corte_procesos lcp ON lcp.id_lista_corte_posicion=pos.id LEFT JOIN tipos_procesos tp ON lcp.id_tipo_proceso=tp.id INNER JOIN listas_corte_conjuntos lcc ON pos.id_lista_corte_conjunto=lcc.id WHERE lcc.id_lista_corte = ".$data["id_lista_corte_revision"]." GROUP BY pos.id";
                                
                                foreach ($pdo->query($sql) as $row) {
                                  echo '<tr>';
                                  echo '<td>'. $row["id"] . '</td>';
                                  echo '<td class="d-none">'. $row["id_conjunto"] . '</td>';
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
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                      <!-- <span id="datosPosicion" class="d-none"> -->
                        <div class="row">
                          <div class="form-group col-3">
                            <label>Posicion(*)</label>
                            <input name="nombre_posicion" type="text" maxlength="99" class="form-control nombre_posicion" value="">
                            <input name="id_posicion" id="id_posicion" type="hidden">
                          </div>
                          <div class="form-group col-3">
                            <label>Cantidad(*)</label>
                            <input name="cantidad_posicion" type="number" maxlength="99" class="form-control cantidad_posicion" value="">
                          </div>
                          <div class="form-group col-3">
                            <label>Concepto(*)</label><br>
                            <select name="id_material" class="js-example-basic-single id_material">
                              <option value="">Seleccione...</option><?php
                              $pdo = Database::connect();
                              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							  $sqlZon = "SELECT m.id, m.codigo, m.concepto, cd.reservado from computos_detalle cd inner join materiales m on m.id = cd.id_material inner join computos c on c.id = cd.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos p on p.id = t.id_proyecto inner join listas_corte_revisiones lcr on lcr.id_proyecto = p.id inner join listas_corte_conjuntos lcc on lcc.id_lista_corte = lcr.id where cd.cancelado = 0 and lcc.id_lista_corte = ".$data["id_lista_corte_revision"];
                              $q = $pdo->prepare($sqlZon);
                              $q->execute();
                              while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                                  echo "<option value='".$fila['id']."'";
                                  echo ">".$fila['concepto']." (".$fila['codigo'].") - Reservado: ".$fila['reservado']."</option>";
                              }
                              Database::disconnect();?>
                            </select>
                          </div>
                        </div>
                        <div class="row">
                          <div class="form-group col-2">
                            <label>Ancho</label>
                            <input name="ancho" type="number" maxlength="99" step="0.01" class="form-control ancho" value="">
                          </div>
                          <div class="form-group col-2">
                            <label>Largo</label>
                            <input name="largo" type="number" maxlength="99" step="0.01" class="form-control largo" value="">
                          </div>
                          <div class="form-group col-2">
                            <label>Diametro</label>
                            <input name="diametro" type="number" maxlength="99" step="0.01" class="form-control diametro" value="">
                          </div>
                          <div class="form-group col-2">
                            <label>Marca</label>
                            <input name="marca" type="text" maxlength="99" class="form-control marca" value="">
                          </div>
                          <div class="form-group col-2">
                            <label>Peso KG</label>
                            <input name="peso" type="number" maxlength="99" step="0.01" class="form-control peso" value="">
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
                            <select name="id_terminacion" class="js-example-basic-single id_terminacion">
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
                      <!-- </span> -->
                    </div>
                  </div>

                  <div class="card mb-0">
                    <div class="card-header">
                      <h5>Datos de la revision</h5>
                    </div>
                    <div class="card-body">
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha(*)</label>
                        <div class="col-sm-9"><input name="fecha" type="date" onfocus="this.showPicker()" class="form-control" required="required" value="<?=$fecha?>"></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Revision(*)</label>
                        <div class="col-sm-9"><input name="revision" type="text" maxlength="99" class="form-control" required="required" value="<?=$nro_revision?>" readonly></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Comentarios(*)</label>
                        <div class="col-sm-9"><textarea name="descripcion" required="required" class="form-control"><?=$descripcion?></textarea></div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Revisó</label>
                        <div class="col-sm-9">
                          <select name="id_cuenta_reviso" id="id_cuenta_reviso" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
                              if ($fila['id']==$data['id_cuenta_reviso']) {
                                echo " selected ";
                              }
                              echo ">".$fila['nombre']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Aprobó</label>
                        <div class="col-sm-9">
                          <select name="id_cuenta_valido" id="id_cuenta_valido" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option><?php
                            $pdo = Database::connect();
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlZon = "SELECT id, nombre FROM cuentas WHERE id_tipo_cuenta in (4) and activo = 1 and anulado = 0";
                            $q = $pdo->prepare($sqlZon);
                            $q->execute();
                            while ($fila = $q->fetch(PDO::FETCH_ASSOC)) {
                              echo "<option value='".$fila['id']."'";
                              if ($fila['id']==$data['id_cuenta_valido']) {
                                echo " selected ";
                              }
                              echo ">".$fila['nombre']."</option>";
                            }
                            Database::disconnect();?>
                          </select>
                        </div>
                      </div>
                      
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <!-- <button type="submit" value="1" name="btn1" class="btn btn-success addPosicion">Crear y Agregar otra Posicion</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addPosicion">Crear y volver a Conjuntos</button> -->
                        <button type="submit" class="btn <?=$classSubmit?>"><?=$titleSubmit?></button>
                        <a href='listarListasCorte.php' class="btn btn-light">Volver</a>
                      </div>
                    </div>
                  </div>

                </form>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas conjuntos -->
        <div class="modal fade" id="eliminarConjunto" tabindex="-1" role="dialog" aria-labelledby="exampleModalConjuntoLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalConjuntoLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el conjunto?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
            </div>
          </div>
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
        var tablaConjuntos = $('#tablaConjuntos');
        // Setup - add a text input to each footer cell
        tablaConjuntos.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaConjuntos.DataTable({
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
 
        // Apply the search
        tablaConjuntos.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        //tablaConjuntos.find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaConjuntos tbody tr td", function(){
          var t=$(this).parent();

          let id_conjunto=t.find("td:first-child").html();
          let tablaPosiciones=$('#tablaPosiciones').DataTable()
          if(t.hasClass('selected')){
            deselectRow(t);
            //volvemos a ocultar todas las posiciones
            tablaPosiciones.rows().nodes().each(function(row) {
              $(row).hide();
            });
            //$("#datosPosicion").addClass("d-none")
            //volvemos a vaciar los links
            $("#link_ver_conjunto_lc").attr("href","#");
            $("#link_eliminar_conjunto").data("id","");
            $("#link_nueva_posicion").attr("href","#");
            //$("#link_modificar_conjunto").attr("href","#");

            //vaciamos los input para permitir crear un nuevo conjunto
            $("input[name='nombre']").val("")
            $("#nombreConjunto").html("")
            $("input[name='cantidad']").val("").attr("data-original","")
            $("#idConjunto").val("")

          }else{
            //t.parent().find("tr").removeClass("selected");
            tablaConjuntos.DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            //mostramos las posiciones del conjunto seleccionado
            tablaPosiciones.rows().nodes().each(function(row) {
              var fila = tablaPosiciones.row(row);
              var datos = fila.data();
              if (datos[1] == id_conjunto) {
                $(row).show();
              } else {
                $(row).hide();
              }
            });
            //$("#datosPosicion").removeClass("d-none")
            //agregamos los links con el id_conjunto correspondiente
            $("#link_ver_conjunto_lc").attr("href","verConjuntoListaCorte.php?id="+id_conjunto);
            $("#link_eliminar_conjunto").data("id",id_conjunto);
            $("#link_nueva_posicion").attr("href","nuevaListaCortePosiciones.php?id_lista_corte_conjunto="+id_conjunto);
            //$("#link_modificar_conjunto").attr("href","modificarListaCorteConjunto.php?id="+id_conjunto);

            //agregamos la funcionalidad para que traiga los datos de la tabla

            //$("#link_modificar_conjunto").on("click",function(){
              let nombre = t.find("td:nth-child(2)").html();
              let cantidad = t.find("td:nth-child(3)").html();
              $("#nombreConjunto").html(nombre)
              $("input[name='nombre']").val(nombre).focus()
              $("input[name='cantidad']").val(cantidad).attr("data-original",cantidad)
              $("#idConjunto").val(id_conjunto)
              
              /*$("#editConjunto").val(id_conjunto)
              $("#editConjuntoGoPosiciones").val(id_conjunto)

              if($("#editConjunto").hasClass("d-none")){
                $("#addConjunto").toggleClass("d-none")
                $("#editConjunto").toggleClass("d-none")
                $("#editConjuntoGoPosiciones").toggleClass("d-none")
                $("#cancelEditConjunto").toggleClass("d-none")
                $("#volverListaCorte").toggleClass("d-none")
              }*/
            //})
            
          }
        });

        $("#link_eliminar_conjunto").on("click",function(){
          let id_conjunto=$(this).data("id")
          if(id_conjunto!="" && id_conjunto>0){
            let modal=$("#eliminarConjunto")
            modal.modal("show")
            modal.find(".modal-footer a").attr("href","eliminarConjuntoListaCorte.php?id="+id_conjunto)
          }else{
            alert("Por favor seleccione un conjunto para eliminar")
          }
        });

        $("#link_ver_conjunto_lc").on("click",function(){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            alert("Por favor seleccione un conjunto para ver el detalle")
            e.preventDefault()
          }
        })

        /*$("#cancelEditConjunto").on("click",function(){
          $("input[name='nombre']").val("")
          $("input[name='cantidad']").val("")
          $("#addConjunto").toggleClass("d-none")
          $("#editConjunto").toggleClass("d-none")
          $("#editConjuntoGoPosiciones").toggleClass("d-none")
          $("#editConjunto").val("")
          $("#editConjuntoGoPosiciones").val("")
          $("#cancelEditConjunto").toggleClass("d-none")
          $("#volverListaCorte").toggleClass("d-none")
        })*/

        var tablaPosiciones = $('#tablaPosiciones');
        var id_estado_lista_corte="<?=$data["id_estado_lista_corte"]?>"
        // Setup - add a text input to each footer cell
        tablaPosiciones.find('tfoot th').each( function () {
          var title = $(this).text();
          $(this).html( '<input type="text" size="'+title.length+'" size="'+title.length+'" placeholder="'+title+'" />' );
        } );
	      tablaPosiciones.DataTable({
          stateSave: false,
          responsive: false,
          "rowCallback": function(row, data) {
            $(row).hide();
          },
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
 
        // Apply the search
        tablaPosiciones.DataTable().columns().every( function () {
          var that = this;
          $( 'input', this.footer() ).on( 'keyup change', function () {
            if ( that.search() !== this.value ) {
              that.search( this.value ).draw();
            }
          });
        } );

        //tablaPosiciones.find("tbody tr td").not(":last-child").on( 'click', function () {
        $(document).on("click","#tablaPosiciones tbody tr td", function(){
          var t=$(this).parent();

          let id_posicion=t.find("td:first-child").html();
          if(t.hasClass('selected')){
            deselectRow(t);
            $("#link_modificar_posicion").attr("href","#");
            $("#link_eliminar_posicion").data("id","");
            $("#link_ver_posicion_lc").attr("href","#");

            $("#id_posicion").val("")
            $("input[name='nombre_posicion']").val("")
            $("input[name='cantidad_posicion']").val("")
            $("select[name='id_material']").val("").trigger('change');
            $("input[name='ancho']").val("")
            $("input[name='largo']").val("")
            $("input[name='diametro']").val("")
            $("input[name='marca']").val("")
            $("input[name='peso']").val("")
            //$("input[name='proceso']").val(id_material)
            $("input[name='proceso[]']").each(function(){
              this.checked=false;
            })
            $("select[name='id_terminacion']").val("").trigger('change');
          }else{
            //t.parent().find("tr").removeClass("selected");
            tablaPosiciones.DataTable().rows().nodes().each( function (rowNode, index) {
              $(rowNode).removeClass("selected");
            });
            selectRow(t);
            //agregamos los links con el id_posicion correspondiente
            $("#link_modificar_posicion").attr("href","modificarPosicionListaCorte.php?id="+id_posicion);
            //$("#link_eliminar_posicion").attr("href","eliminarPosicionListaCorte.php?id="+id_posicion);
            $("#link_eliminar_posicion").data("id",id_posicion);
            $("#link_ver_posicion_lc").attr("href","verPosicionConjuntoListaCorte.php?id="+id_posicion);

            //$("#link_modificar_posicion").on("click",function(){
              $("#id_posicion").val(id_posicion)
              let posicion = t.find("td:nth-child(3)").html();
              let cantidad = t.find("td:nth-child(4)").html();
              let id_material = t.find("td:nth-child(5)").data("id");
              let ancho = t.find("td:nth-child(6)").html();
              let largo = t.find("td:nth-child(7)").html();
              let diametro = t.find("td:nth-child(8)").html();
              let marca = t.find("td:nth-child(9)").html();
              let peso = t.find("td:nth-child(10)").html();
              let procesos = t.find("td:nth-child(11)").data("id");
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
              //$("input[name='proceso']").val(id_material)
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

              /*$("#editPosicion").val(id_posicion)
              if($("#editPosicion").hasClass("d-none")){
                $(".addPosicion").toggleClass("d-none")
                $("#editPosicion").toggleClass("d-none")
                $("#cancelEditPosicion").toggleClass("d-none")
              }*/
            //})
          }
        });

        /*$("#link_eliminar_posicion").on("click",function(){
          let id_posicion=$(this).data("id")
          if(id_posicion!="" && id_posicion>0){
            let modal=$("#eliminarPosicion")
            modal.modal("show")
            modal.find(".modal-footer a").attr("href","eliminarPosicionListaCorte.php?id="+id_posicion)
          }
        });*/

        $("#link_eliminar_posicion").on("click",function(){
          let id_posicion=$(this).data("id")
          if(id_posicion!="" && id_posicion>0){
            let modal=$("#eliminarPosicion")
            modal.modal("show")
            modal.find(".modal-footer a").attr("href","eliminarPosicionListaCorte.php?id="+id_posicion)
          }else{
            alert("Por favor seleccione una posicion para eliminar")
          }
        });

        $("#link_ver_posicion_lc").on("click",function(e){
          let l=document.location.href;
          if(this.href==l || this.href==l+"#"){
            e.preventDefault()
            alert("Por favor seleccione una posicion para ver el detalle")
          }
        })

        /*$("#cancelEditPosicion").on("click",function(){
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
        })*/
    
      });

      function selectRow(t){
        t.addClass('selected');
      }
      function deselectRow(t){
        t.removeClass('selected');
      }
    </script>
  </body>
</html>