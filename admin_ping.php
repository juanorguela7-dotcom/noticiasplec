<?php
/* Mantiene viva la sesión del panel mientras el administrador trabaja.
   El JS lo llama cada pocos minutos SOLO si hubo actividad (teclear,
   mover el mouse…). Antes, escribir una nota larga sin recargar dejaba
   vencer la sesión en el servidor aunque el panel no avisara nada. */
$_SERVER['HTTP_X_PLEC_AJAX'] = '1';   // siempre responde JSON, nunca redirige
require __DIR__ . '/admin_comun.php';

plec_admin_json([
    'ok'      => true,
    'csrf'    => plec_admin_csrf(),
    'timeout' => PLEC_ADMIN_TIMEOUT,
]);
