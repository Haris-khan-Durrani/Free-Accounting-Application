<?php
require __DIR__.'/bootstrap.php'; require_login(); if($_SERVER['REQUEST_METHOD']!=='POST')redirect('index.php'); verify_csrf();$id=(int)($_POST['id']??0);$st=$pdo->prepare('DELETE FROM invoices WHERE id=?');$st->execute([$id]);flash('success','Invoice deleted.');redirect('index.php');
