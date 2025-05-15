<?php 
function tienePermiso($p) {
	
	$idPermiso = in_array($p,$_SESSION['user']['permisos']);
	return $idPermiso;	
}
?>