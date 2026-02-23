<?php
require("config.php");

if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}

require 'database.php';
require_once('funciones.php');

$id = $_GET['id'] ?? null;

if (null == $id) {
    die("Falta el parámetro ?id=");
}

echo "<h2>Debug Notificación - Pedido #$id</h2>";

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1) Verificar getSmtpConfig
echo "<h3>1. Configuración SMTP</h3>";
$smtp = getSmtpConfig($pdo);
echo "<pre>";
echo "Host:       " . $smtp[0] . "\n";
echo "Usuario:    " . $smtp[1] . "\n";
echo "Clave:      " . str_repeat("*", strlen($smtp[2])) . "\n";
echo "From:       " . $smtp[3] . "\n";
echo "FromName:   " . $smtp[4] . "\n";
echo "Port:       " . var_export($smtp[5], true) . "\n";
echo "SMTPSecure: " . var_export($smtp[6], true) . "\n";
echo "</pre>";

// 2) Verificar destinatarios para tipo 3 (pedidos)
echo "<h3>2. Destinatarios para tipo_notificacion = 3</h3>";
$sql = "SELECT t.id_usuario, u.email 
        FROM usuarios_tipos_notificacion t 
        JOIN usuarios u ON u.id = t.id_usuario 
        WHERE t.id_tipo_notificacion = 3";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "<p style='color:red;font-weight:bold;'>❌ NO HAY DESTINATARIOS - El foreach nunca se ejecuta</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID Usuario</th><th>Email</th></tr>";
    foreach ($rows as $row) {
        echo "<tr><td>{$row['id_usuario']}</td><td>{$row['email']}</td></tr>";
    }
    echo "</table>";
}

// 3) Probar envío de email directamente
echo "<h3>3. Prueba de envío de email</h3>";

if (!empty($rows)) {
    list($smtpHost, $smtpUsuario, $smtpClave, $smtpFrom, $smtpFromName, $smtpPort, $smtpSecure) = $smtp;
    
    $destEmail = $rows[0]['email'];
    echo "Intentando enviar a: <b>$destEmail</b><br><br>";
    
    $mail = new PHPMailer();
    $mail->SMTPDebug = 2;  // ← MUESTRA TODA LA CONVERSACIÓN SMTP EN PANTALLA
    $mail->Debugoutput = 'html'; // ← Formato legible en el navegador
    $mail->IsSMTP();
    $mail->Host       = $smtpHost;
    $mail->Username   = $smtpUsuario;
    $mail->Password   = $smtpClave;
    $mail->SMTPAuth   = true;
    $mail->Port       = $smtpPort;
    $mail->SMTPSecure = $smtpSecure;
    $mail->From       = $smtpFrom;
    $mail->FromName   = $_SESSION['user']['usuario'];
    $mail->CharSet    = "utf-8";
    $mail->IsHTML(true);
    $mail->AddAddress($destEmail);
    $mail->Subject    = "TEST - Debug notificación pedido #$id";
    $mail->Body       = "Este es un email de prueba para el pedido #$id";
    $mail->AltBody    = "Este es un email de prueba para el pedido #$id";
    
    echo "<div style='background:#f0f0f0; padding:10px; border:1px solid #ccc;'>";
    $envio = $mail->Send();
    echo "</div>";
    
    echo "<br><br>";
    if ($envio) {
        echo "<p style='color:green;font-weight:bold;'>✅ EMAIL ENVIADO CORRECTAMENTE</p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>❌ ERROR: " . $mail->ErrorInfo . "</p>";
    }

    // 4) Comparar con la config que SÍ funciona (hardcodeada)
    echo "<h3>4. Prueba con config hardcodeada (como el código viejo)</h3>";
    
    $mail2 = new PHPMailer();
    $mail2->SMTPDebug = 2;
    $mail2->Debugoutput = 'html';
    $mail2->IsSMTP();
    $mail2->Host       = $smtpHost;
    $mail2->Username   = $smtpUsuario;
    $mail2->Password   = $smtpClave;
    $mail2->SMTPAuth   = true;
    $mail2->Port       = 587;        // ← HARDCODEADO como el código viejo
    $mail2->SMTPSecure = 'tls';      // ← HARDCODEADO como el código viejo
    $mail2->From       = $smtpFrom;
    $mail2->FromName   = $_SESSION['user']['usuario'];
    $mail2->CharSet    = "utf-8";
    $mail2->IsHTML(true);
    $mail2->AddAddress($destEmail);
    $mail2->Subject    = "TEST 2 - Debug con config hardcodeada pedido #$id";
    $mail2->Body       = "Este es un email de prueba con config hardcodeada";
    $mail2->AltBody    = "Este es un email de prueba con config hardcodeada";
    
    echo "<div style='background:#f0f0f0; padding:10px; border:1px solid #ccc;'>";
    $envio2 = $mail2->Send();
    echo "</div>";
    
    echo "<br><br>";
    if ($envio2) {
        echo "<p style='color:green;font-weight:bold;'>✅ EMAIL ENVIADO CORRECTAMENTE (hardcodeado)</p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>❌ ERROR (hardcodeado): " . $mail2->ErrorInfo . "</p>";
    }

    // 5) Mostrar la diferencia
    echo "<h3>5. Comparación</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th></th><th>getSmtpConfig()</th><th>Hardcodeado (viejo)</th></tr>";
    echo "<tr><td>Port</td><td>" . var_export($smtpPort, true) . "</td><td>587</td></tr>";
    echo "<tr><td>SMTPSecure</td><td>" . var_export($smtpSecure, true) . "</td><td>'tls'</td></tr>";
    echo "<tr><td>Resultado</td><td>" . ($envio ? '✅' : '❌') . "</td><td>" . ($envio2 ? '✅' : '❌') . "</td></tr>";
    echo "</table>";
}

Database::disconnect();
?>