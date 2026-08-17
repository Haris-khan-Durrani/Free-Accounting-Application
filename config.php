<?php
return [
  'db_host' => getenv('DB_HOST') ?: 'localhost',
  'db_port' => getenv('DB_PORT') ?: '3306',
  'db_name' => getenv('DB_NAME') ?: 'onesol_invoices',
  'db_user' => getenv('DB_USER') ?: 'root',
  'db_pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
];
