<style>
        table {
            border-collapse: collapse; /* Ensures no space between table cells */
            width: 80%;
        }
        th, td {
            border: 0px;
            text-align: left;
        }
		.dataTables_wrapper table.dataTable th, .dataTables_wrapper table.dataTable td {
     padding: 0.30rem; 
}
    </style>
<div class="page-main-header">
        <div class="main-header-right row">
          <div class="main-header-left d-lg-none">
            <div class="logo-wrapper"><a href="index.html"><img src="assets/images/logo.jpg" width="" alt=""></a></div>
          </div>
          <div class="mobile-sidebar d-block">
            <div class="media-body text-right switch-sm">
              <label class="switch"><a href="#"><i id="sidebar-toggle" data-feather="align-left"></i></a></label>
            </div>
          </div>
          <div class="nav-right col p-0">
            <ul class="nav-menus">
              <li align="left">
				<h5><b>GRUPONH - SISTEMA DE GESTIÓN</b></h5>
              </li>
             
              <li><a  target="_blank" data-container="body" data-toggle="popover" data-placement="top" title="" data-original-title="<?php echo date('d-m-Y');?>"><i data-feather="calendar"></i></a></li>
			  
			  <li class="onhover-dropdown"><i data-feather="send"></i>
			  <?php
			    require 'databaseHeader.php';
			    $pdoH = Database2::connect();
				$pdoH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$sqlH = "SELECT count(*) cant FROM `notificaciones` n inner join tipos_notificacion t on t.id = n.`id_tipo_notificacion` WHERE n.`leida` = 0 and n.`id_usuario` = ?";
				$qH = $pdoH->prepare($sqlH);
				$qH->execute([$_SESSION['user']['id']]);
				$dataH = $qH->fetch(PDO::FETCH_ASSOC);
				if ($dataH['cant'] > 0) {
					echo '<span class="dot"></span>';
				}
			  ?>		
			  

			  <ul class="notification-dropdown onhover-show-div">
                  <li>Notificaciones <span class="badge badge-pill badge-primary pull-right"><?php echo $dataH['cant'];?></span></li>
				  <?php
				  $sql = " SELECT n.`id`, t.tipo, t.mensaje, date_format(n.`fecha_hora`,'%d/%m/%Y %H:%i'), n.`detalle`,t.redirect,n.id_entidad FROM `notificaciones` n inner join tipos_notificacion t on t.id = n.`id_tipo_notificacion` WHERE n.`leida` = 0 and n.`id_usuario` = ".$_SESSION['user']['id']." order by n.`id` desc ";
				  foreach ($pdoH->query($sql) as $row) {
				  ?>
				  <li>
                    <div class="media">
                      <div class="media-body">
                        <h6 class="mt-0"><?php echo $row[1] ?><small class="pull-right"><?php echo $row[3] ?>hs</small></h6>
                        <p class="mb-0"><?php echo $row[2] ?></p>
						<p class="mb-0"><i><u><a href="marcarNotificacionLeida.php?id=<?php echo $row[0]; ?>&returnURL=<?php echo $row[5] ?><?php echo $row[6] ?>" title="Ir" alt="Ir"><span style="color: blue;"><?php echo $row[4] ?></span></a></u></i>&nbsp;
						<a href="eliminarNotificacion.php?id=<?php echo $row[0]; ?>" title="Borrar" alt="Borrar"><img src="img/icon_baja.png" width="15px"></a></p>
                      </div>
                    </div>
                  </li>
				  <?php
				  }
				  ?>
                </ul>
              </li>
			  <li class="onhover-dropdown">
                  <h6><b><?php echo $_SESSION['user']['usuario']?></b></h6>
              </li>
              <li class="onhover-dropdown">
                <div class="media align-items-center"><a href="logout.php"><img class="align-self-center pull-right rounded-circle" src="assets/images/cerrar-sesion.png" width="25px" alt="header-user"></a>
                </div>
              </li>
            </ul>
            <div class="d-lg-none mobile-toggle pull-right"><i data-feather="more-horizontal"></i></div>
          </div>
          
        </div>
      </div>