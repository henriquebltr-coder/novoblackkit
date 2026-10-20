<?php

// 1. Função para descobrir o IP real do usuário (ignorando proxies)
function obter_ip_real() {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $ip){
                $ip = trim($ip); 
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

// 2. Verificação de País (EUA)
$is_us = false;

// Otimização: Se você usa Cloudflare ou Vercel, eles já enviam o país no cabeçalho. Isso evita lentidão na página.
if (isset($_SERVER["HTTP_CF_IPCOUNTRY"]) && $_SERVER["HTTP_CF_IPCOUNTRY"] === 'US') {
    $is_us = true;
} elseif (isset($_SERVER["HTTP_X_VERCEL_IP_COUNTRY"]) && $_SERVER["HTTP_X_VERCEL_IP_COUNTRY"] === 'US') {
    $is_us = true;
} else {
    // Fallback: Consulta via API gratuita (pode adicionar milissegundos de delay no carregamento)
    $ip = obter_ip_real();
    if ($ip !== 'UNKNOWN') {
        $ch = curl_init("http://ip-api.com/json/{$ip}?fields=countryCode");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Timeout de 2s para não travar o site
        $resposta = curl_exec($ch);
        curl_close($ch);
        
        if ($resposta) {
            $dados = json_decode($resposta, true);
            if (isset($dados['countryCode']) && $dados['countryCode'] === 'US') {
                $is_us = true;
            }
        }
    }
}

// 3. Verificação de Mobile
$user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$is_mobile = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent);

// 4. Verificação de Bot
// Bloqueia spiders de busca e rastreadores de redes sociais (como o bot do Facebook/Meta)
$is_bot = preg_match('/(bot|crawl|spider|slurp|facebookexternalhit|googlebot|bingbot|yandexbot|duckduckbot|baiduspider|tiktokbot)/i', $user_agent);

// 5. Verificação de UTMs padrão
$has_utms = isset($_GET['utm_source']) && 
            isset($_GET['utm_campaign']) && 
            isset($_GET['utm_medium']) && 
            isset($_GET['utm_content']) && 
            isset($_GET['utm_term']);

// 6. Verificação do parâmetro específico e aleatório
$has_specific_param = (isset($_GET['utm_red']) && $_GET['utm_red'] === 'dwidbw');

// 7. Lógica de Redirecionamento
if ($is_us && $is_mobile && !$is_bot && $has_utms && $has_specific_param) {
    // Mantém todos os parâmetros na URL para não quebrar seu rastreamento no GA4 ou UTMify
    $query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: https://www.elmontblack.com/" . $query_string);
    exit;
} else {
    // Tráfego desqualificado (bots, desktop, fora dos EUA ou sem os parâmetros corretos)
    header("Location: https://www.amazon.com/Turesmith-Organizer-Organizador-Perfumes-Countertop/dp/B0DYV1H28G/ref=sr_1_1?crid=1RTU6MPBUPAQA&dib=eyJ2IjoiMSJ9.QOfDJKIRWdoHWKosjQbMDrvDd4RgBoZM24cQnvHY4T8PM6Ikx1atwnpfDepZMo0ye_W9q8hYyNuWKDrYXu-s446TEMecckKNVsl1PBOonS8s2WBQkeQBQdQSZNBwvx0RDSn0xuHFUw1xVyzCOlqAhRYpsv8KgP2kNPG4KrWzvv6bDRd2YCmUlB0OuVZ1pRs6SUjsZivZ9tBiLKKvg1z6ND01wdToG0BjwEQceLnH-SVCD0xNeLwMwYQpAQPjOTvsKFp0gb1noFs1s5sgJMFSjGmyVc-NKY8y8Zy8CEZRnI0.UH4CGantAmxyzwEPqGQIJLO5cjEoxUbmMTjkdhoyRN8&dib_tag=se&keywords=cologne+organizer&qid=1792525768&sprefix=cologne+organize%2Caps%2C240&sr=8-1");
    exit;
}
?>
