<?php
/**
 * Convierte la URL pública de un video (YouTube, Vimeo, Facebook, TikTok,
 * Instagram, Twitch) en su URL de inserción. Devuelve '' si no la reconoce.
 */
function obtenerEmbedVideo($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    $u = strtolower($url);

    if (strpos($u, 'youtube.com') !== false || strpos($u, 'youtu.be') !== false) {
        if (preg_match('~(?:watch\?(?:.*&)?v=|/live/|/shorts/|/embed/|youtu\.be/)([A-Za-z0-9_-]{5,20})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0';
        }
        return '';
    }
    if (strpos($u, 'vimeo.com') !== false) {
        return preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m) ? 'https://player.vimeo.com/video/' . $m[1] : '';
    }
    if (strpos($u, 'facebook.com') !== false || strpos($u, 'fb.watch') !== false) {
        return 'https://www.facebook.com/plugins/video.php?href=' . urlencode($url) . '&show_text=false';
    }
    if (strpos($u, 'tiktok.com') !== false) {
        return preg_match('~/video/(\d+)~', $url, $m) ? 'https://www.tiktok.com/embed/v2/' . $m[1] : '';
    }
    if (strpos($u, 'instagram.com') !== false) {
        return preg_match('~instagram\.com/(?:p|reel)/([A-Za-z0-9_-]+)~', $url, $m) ? 'https://www.instagram.com/p/' . $m[1] . '/embed' : '';
    }
    if (strpos($u, 'twitch.tv') !== false) {
        $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        return preg_match('~twitch\.tv/([A-Za-z0-9_]+)~', $url, $m)
            ? 'https://player.twitch.tv/?channel=' . $m[1] . '&parent=' . rawurlencode($host)
            : '';
    }
    return '';
}

/**
 * HTML responsivo (16:9) con el video insertado, o '' si la URL no es válida.
 */
function renderizarVideoEmbed($url) {
    $embed = obtenerEmbedVideo($url);
    if ($embed === '') return '';
    return '<div class="plec-video" style="position:relative; width:100%; padding-bottom:56.25%; margin:22px 0; border-radius:10px; overflow:hidden; background:#000; box-shadow:0 20px 40px -18px rgba(0,0,0,.8);">'
         . '<iframe style="position:absolute; inset:0; width:100%; height:100%; border:none;" src="' . htmlspecialchars($embed, ENT_QUOTES, 'UTF-8') . '"'
         . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>'
         . '</div>';
}

/**
 * Indica si la URL pertenece a una plataforma de video soportada.
 */
function validarURLVideo($url) {
    return obtenerEmbedVideo($url) !== '';
}
