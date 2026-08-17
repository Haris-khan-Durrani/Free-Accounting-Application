<?php
session_start();
$error = '';
$installed = file_exists(__DIR__ . '/config.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $host=trim($_POST['db_host']??'localhost'); $port=trim($_POST['db_port']??'3306'); $db=trim($_POST['db_name']??'onesol_invoices');
    $user=trim($_POST['db_user']??'root'); $pass=$_POST['db_pass']??''; $email=trim($_POST['admin_email']??'admin@onesol.ae'); $adminPass=$_POST['admin_password']??'';
    if (!$db || !$user || !$email || strlen($adminPass) < 8) $error='Please complete all required fields. Admin password must be at least 8 characters.';
    else {
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $safeDb = str_replace('`','',$db);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$safeDb}`");
            $sql=file_get_contents(__DIR__.'/database.sql'); $pdo->exec($sql);
            $hash=password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
            $stmt->execute(['OneSol Admin',$email,$hash]);

            // Seed the current 360 Business Consultants proposal invoice.
            $count=(int)$pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
            if($count===0){
                $st=$pdo->prepare('INSERT INTO clients(company_name,address) VALUES(?,?)');
                $st->execute(['360 Business Consultants','Dubai, United Arab Emirates']);
                $clientId=(int)$pdo->lastInsertId();
                $notes="This proposal is based on the mutually agreed project scope and deliverables.\nAny additional scope, integrations or major changes may be quoted separately.\nPayment schedule and project start date will follow the agreed confirmation.\nThis document is a proposal / proforma invoice and is not a tax invoice.";
                $st=$pdo->prepare('INSERT INTO invoices(invoice_number,client_id,invoice_date,valid_until,status,subtotal,discount_type,discount_value,discount_amount,total,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute(['OS-PI-20260807-001',$clientId,'2026-08-07','2026-08-22','draft',4000,'fixed',1500,1500,2500,$notes]);
                $invoiceId=(int)$pdo->lastInsertId();
                $st=$pdo->prepare('INSERT INTO invoice_items(invoice_id,description,details,qty,unit_price,amount,sort_order) VALUES(?,?,?,?,?,?,0)');
                $st->execute([$invoiceId,'Software Development & Implementation Services','Includes setup, configuration, customization and delivery as per agreed scope.',1,4000,4000]);
            }
            $config="<?php\nreturn ".var_export(['db_host'=>$host,'db_port'=>$port,'db_name'=>$db,'db_user'=>$user,'db_pass'=>$pass],true).";\n";
            if (file_put_contents(__DIR__.'/config.php',$config)===false) throw new RuntimeException('Could not write config.php.');
            header('Location: login.php?installed=1'); exit;
        } catch (Throwable $e) { $error='Installation failed: '.$e->getMessage(); }
    }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install OneSol Invoice Manager</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="auth-bg"><div class="auth-card wide"><img class="auth-logo" src="assets/img/onesol-logo.png"><h1>Install Invoice Manager</h1>
<?php if($installed): ?><div class="alert success">Application is already installed. <a href="login.php">Go to login</a>.</div><?php else: ?>
<?php if($error): ?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post" class="grid2"><label>DB Host<input name="db_host" value="localhost" required></label><label>DB Port<input name="db_port" value="3306" required></label><label>Database Name<input name="db_name" value="onesol_invoices" required></label><label>DB User<input name="db_user" value="root" required></label><label class="span2">DB Password<input type="password" name="db_pass"></label><label>Admin Email<input type="email" name="admin_email" value="admin@onesol.ae" required></label><label>Admin Password<input type="password" name="admin_password" minlength="8" required></label><button class="btn btn-gold span2" type="submit">Install Application</button></form>
<?php endif; ?></div></body></html>
