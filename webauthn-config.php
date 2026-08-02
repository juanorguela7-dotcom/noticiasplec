<?php
// ── CONFIGURACIÓN COMPARTIDA DE WEBAUTHN ──
// Este archivo lo incluyen todos los endpoints de huella (registro y login).

require_once __DIR__ . '/vendor/autoload.php';

// ── SESIÓN SEGURA: igual que en login.php, debe ir antes de session_start() ──
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 1800);
ini_set('session.cookie_secure', 1); // Railway ya sirve todo por HTTPS

session_start();
include(__DIR__ . '/conexion.php');

// ── RP ID: el dominio desde el que se sirve el sitio (sin protocolo ni puerto) ──
// En Railway normalmente es algo como "tuapp.up.railway.app" o tu dominio propio.
$rpId = explode(':', $_SERVER['HTTP_HOST'])[0];

$WebAuthn = new \lbuchs\WebAuthn\WebAuthn('Noticias PLEC', $rpId);

// Respuesta JSON estándar de error, para no repetir código
function passkey_error($mensaje, $codigo = 400) {
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode(['error' => $mensaje]);
    exit();
}