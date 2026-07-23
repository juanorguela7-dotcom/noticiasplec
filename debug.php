<?php
header('Content-Type: text/plain');
echo "=== Variables de entorno detectadas (getenv) ===\n";
print_r(getenv());

echo "\n=== \$_SERVER (solo las que no sean HTTP) ===\n";
foreach ($_SERVER as $key => $value) {
    if (stripos($key, 'MYSQL') !== false || stripos($key, 'RAILWAY') !== false) {
        echo "$key = $value\n";
    }
}

echo "\n=== \$_ENV completo ===\n";
print_r($_ENV);