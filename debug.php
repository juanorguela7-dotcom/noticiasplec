<?php
header('Content-Type: text/plain');
echo "getenv MYSQLHOST: " . var_export(getenv('MYSQLHOST'), true) . "\n";
echo "ENV MYSQLHOST: " . var_export($_ENV['MYSQLHOST'] ?? 'NO EXISTE', true) . "\n";
echo "SERVER MYSQLHOST: " . var_export($_SERVER['MYSQLHOST'] ?? 'NO EXISTE', true) . "\n";