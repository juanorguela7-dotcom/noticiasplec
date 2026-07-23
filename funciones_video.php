<?php
/**
 * Función para generar embed HTML responsivo según plataforma
 * @param string $url - URL del video
 * @return string - HTML del iframe responsivo
 */
function renderizarVideoEmbed($url) {
    if (empty($url)) {
        return '';
    }
    
    $urlLower = strtolower($url);
    $embedUrl = null;
    
    // YOUTUBE
    if (strpos($urlLower, 'youtube.com') !== false || strpos($urlLower, 'youtu.be') !== false) {
        $videoId = null;
        if (strpos($url, 'watch?v=') !== false) {
            $videoId = explode('watch?v=', $url)[1];
            $videoId = explode('&', $videoId)[0];
        } elseif (strpos($url, 'youtu.be/') !== false) {
            $videoId = explode('youtu.be/', $url)[1];
            $videoId = explode('?', $videoId)[0];
        }
        if ($videoId) {
            $embedUrl = "https://www.youtube.com/embed/{$videoId}";
        }
    }
    
    // VIMEO
    elseif (strpos($urlLower, 'vimeo.com') !== false) {
        $videoId = explode('vimeo.com/', $url)[1];
        $videoId = explode('?', $videoId)[0];
        $embedUrl = "https://player.vimeo.com/video/{$videoId}";
    }
    
    // TIKTOK
    elseif (strpos($urlLower, 'tiktok.com') !== false) {
        // TikTok puede necesitar oEmbed, por ahora devolvemos link
        return "<div class='alert alert-info'><i class='fas fa-info-circle me-2'></i> <a href='{$url}' target='_blank' rel='noopener'>Ver en TikTok</a></div>";
    }
    
    // FACEBOOK
    elseif (strpos($urlLower, 'facebook.com') !== false) {
        $embedUrl = "https://www.facebook.com/plugins/video.php?href=" . urlencode($url);
    }
    
    // INSTAGRAM
    elseif (strpos($urlLower, 'instagram.com') !== false) {
        if (strpos($url, '/p/') !== false) {
            $postId = explode('/p/', $url)[1];
            $postId = explode('/', $postId)[0];
            $embedUrl = "https://www.instagram.com/p/{$postId}/embed";
        }
    }
    
    // TWITCH
    elseif (strpos($urlLower, 'twitch.tv') !== false) {
        $canal = explode('twitch.tv/', $url)[1];
        $canal = explode('/', $canal)[0];
        $embedUrl = "https://player.twitch.tv/?channel={$canal}&parent=localhost";
    }
    
    // Si no se reconoce, devolver URL de descarga
    if (!$embedUrl) {
        return "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle me-2'></i> URL de video no soportada</div>";
    }
    
    // Retornar HTML responsivo
    $html = "
    <div style='position:relative; width:100%; padding-bottom:56.25%; margin:20px 0; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);'>
        <iframe 
            style='position:absolute; top:0; left:0; width:100%; height:100%; border:none;'
            src='{$embedUrl}'
            allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
            allowfullscreen>
        </iframe>
    </div>
    ";
    
    return $html;
}

/**
 * Función para validar si una URL es de una plataforma soportada
 * @param string $url - URL a validar
 * @return bool
 */
function validarURLVideo($url) {
    if (empty($url)) {
        return false;
    }
    
    $urlLower = strtolower($url);
    $plataformas = ['youtube.com', 'youtu.be', 'vimeo.com', 'tiktok.com', 'facebook.com', 'instagram.com', 'twitch.tv'];
    
    foreach ($plataformas as $plat) {
        if (strpos($urlLower, $plat) !== false) {
            return true;
        }
    }
    
    return false;
}
?>
