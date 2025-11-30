<?php
/**
 * =======================================================================
 * FILE: rate_limit.php
 * DESCRIPTION: Rate limiting untuk mencegah spam email
 * =======================================================================
 */

/**
 * Cek apakah email sudah mencapai batas pengiriman
 * @param string $email Email address
 * @param int $maxAttempts Maksimal percobaan (default: 3)
 * @param int $timeWindow Window waktu dalam detik (default: 3600 = 1 jam)
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function checkRateLimit($email, $maxAttempts = 3, $timeWindow = 3600) {
    $logFile = dirname(__DIR__) . '/logs/email_log.json';
    $logDir = dirname($logFile);
    
    // Buat direktori logs jika belum ada
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Baca log file
    $logs = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $logs = json_decode($content, true) ?: [];
    }
    
    $currentTime = time();
    $emailKey = strtolower(trim($email));
    
    // Bersihkan log lama (lebih dari timeWindow)
    if (isset($logs[$emailKey])) {
        $logs[$emailKey] = array_filter($logs[$emailKey], function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });
        
        // Re-index array
        $logs[$emailKey] = array_values($logs[$emailKey]);
    }
    
    // Hitung jumlah pengiriman dalam timeWindow
    $attemptCount = isset($logs[$emailKey]) ? count($logs[$emailKey]) : 0;
    
    // Hitung waktu reset (timestamp terakhir + timeWindow)
    $resetTime = 0;
    if (isset($logs[$emailKey]) && !empty($logs[$emailKey])) {
        $lastTimestamp = max($logs[$emailKey]);
        $resetTime = $lastTimestamp + $timeWindow;
    }
    
    $allowed = $attemptCount < $maxAttempts;
    $remaining = max(0, $maxAttempts - $attemptCount);
    
    return [
        'allowed' => $allowed,
        'remaining' => $remaining,
        'reset_time' => $resetTime,
        'attempt_count' => $attemptCount
    ];
}

/**
 * Record pengiriman email ke log
 * @param string $email Email address
 */
function recordEmailSent($email) {
    $logFile = dirname(__DIR__) . '/logs/email_log.json';
    $logDir = dirname($logFile);
    
    // Buat direktori logs jika belum ada
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Baca log file
    $logs = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $logs = json_decode($content, true) ?: [];
    }
    
    $emailKey = strtolower(trim($email));
    $currentTime = time();
    
    // Tambahkan timestamp baru
    if (!isset($logs[$emailKey])) {
        $logs[$emailKey] = [];
    }
    
    $logs[$emailKey][] = $currentTime;
    
    // Simpan kembali ke file
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Format waktu tersisa menjadi string yang readable
 * @param int $timestamp Timestamp
 * @return string Formatted time
 */
function formatTimeRemaining($timestamp) {
    $remaining = $timestamp - time();
    
    if ($remaining <= 0) {
        return 'sekarang';
    }
    
    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . ' jam ' . $minutes . ' menit';
    } else {
        return $minutes . ' menit';
    }
}

