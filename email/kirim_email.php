<?php
/**
 * =======================================================================
 * FILE: kirim_email.php
 * DESCRIPTION: Menerima input dari formulir dan mengirim email
 * =======================================================================
 */

// Aktifkan output buffering untuk menangkap semua output
ob_start();

// Set error reporting (nonaktifkan untuk production, aktifkan untuk debugging)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error langsung

// Set header untuk JSON response
header('Content-Type: application/json; charset=utf-8');

// Set CORS headers (sesuaikan dengan kebutuhan)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Sertakan namespace dari PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Sertakan file-file PHPMailer yang dibutuhkan
// Pastikan path ini sesuai dengan struktur folder Anda!
$vendorPath = dirname(__DIR__) . '/vendor/phpmailer/';

if (!file_exists($vendorPath . 'Exception.php')) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'File PHPMailer tidak ditemukan. Pastikan vendor/phpmailer terinstall dengan benar.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $vendorPath . 'Exception.php'; 
require_once $vendorPath . 'PHPMailer.php';
require_once $vendorPath . 'SMTP.php';

// Load konfigurasi SMTP
$configPath = dirname(__DIR__) . '/config/config.php';

if (!file_exists($configPath)) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'File konfigurasi tidak ditemukan. Pastikan config/config.php sudah dibuat dari config.example.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configPath;

// Validasi konfigurasi
if (!isset($config['smtp']) || empty($config['smtp']['username']) || empty($config['smtp']['password'])) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Konfigurasi SMTP tidak lengkap. Pastikan username dan password sudah diisi di config/config.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Bersihkan output buffer sebelum memproses
ob_clean();

/**
 * Validasi email format
 * @param string $email Email address
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Kirim response JSON
 * @param bool $success Status sukses/gagal
 * @param string $message Pesan response
 * @param mixed $data Data tambahan (optional)
 */
function sendJsonResponse($success, $message, $data = null) {
    // Bersihkan semua output sebelumnya
    ob_clean();
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Cek method request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method tidak diizinkan. Gunakan POST.');
}

// Cek apakah formulir telah dikirim dan memiliki data 'email'
if (!isset($_POST['email']) || empty($_POST['email'])) {
    sendJsonResponse(false, 'Email tidak boleh kosong.');
}

// Ambil dan sanitasi data dari formulir
$email_tujuan = trim($_POST['email']);

// Validasi format email
if (!isValidEmail($email_tujuan)) {
    sendJsonResponse(false, 'Format email tidak valid.');
}

// Include rate limiting
require_once __DIR__ . '/rate_limit.php';

// Cek rate limit (maksimal 3x per jam)
$rateLimit = checkRateLimit($email_tujuan, 3, 3600);

if (!$rateLimit['allowed']) {
    $resetTime = formatTimeRemaining($rateLimit['reset_time']);
    sendJsonResponse(
        false, 
        'Anda sudah mencapai batas pengiriman email. Silakan coba lagi dalam ' . $resetTime . '.'
    );
}

// Tentukan Subjek dan Isi Email
$subjek = "Notifikasi Email Otomatis Berhasil Terkirim";
$body_email = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            h3 { color: #667eea; }
            p { margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h3>Halo, ini adalah Pemberitahuan dari web notification_email!</h3>
            <p>Email ini adalah uji coba notifikasi pada web notification_email. 
            Jika Anda menerima ini, berarti konfigurasi sistem terlah berhasil!</p>
            <p>Terima kasih telah mengunjungi web notification_email.</p>
        </div>
    </body>
    </html>
";

// Inisiasi objek PHPMailer
$mail = new PHPMailer(true); // 'true' mengaktifkan mode Exception

try {
    // --- PENGATURAN SERVER SMTP (dari config) ---
    $smtpConfig = $config['smtp'];
    
    $mail->isSMTP(); 
    $mail->Host       = $smtpConfig['host'];
    $mail->SMTPAuth   = $smtpConfig['auth'];
    $mail->Username   = $smtpConfig['username'];
    $mail->Password   = $smtpConfig['password'];
    
    // Atur Enkripsi dan Port
    $mail->SMTPSecure = $smtpConfig['secure'] === 'ssl' 
        ? PHPMailer::ENCRYPTION_SMTPS 
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtpConfig['port'];
    
    // Opsi tambahan jika diperlukan (misalnya untuk debugging)
    if (isset($config['options']['debug']) && $config['options']['debug'] !== false) {
        $mail->SMTPDebug = is_numeric($config['options']['debug']) 
            ? (int)$config['options']['debug'] 
            : 2;
    }

    // --- PENGATURAN PENGIRIM DAN PENERIMA ---
    // Alamat pengirim dari config
    $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']); 
    
    // Tambahkan alamat penerima (dari input formulir)
    $mail->addAddress($email_tujuan); 

    // --- KONTEN EMAIL ---
    $mail->isHTML(true); // Set email format ke HTML
    $mail->Subject = $subjek;
    $mail->Body    = $body_email;
    $mail->AltBody = strip_tags($body_email); // Versi teks biasa (fallback)

    // --- KIRIM EMAIL ---
    $mail->send();
    
    // Record pengiriman ke log untuk rate limiting
    recordEmailSent($email_tujuan);
    
    // Response sukses
    $remaining = $rateLimit['remaining'] - 1;
    $message = 'Notifikasi berhasil dikirim!';
    
    sendJsonResponse(
        true, 
        $message, 
        [
            'email' => $email_tujuan,
            'remaining' => $remaining
        ]
    );

} catch (Exception $e) {
    // Response error - ambil error info dengan aman
    $errorMessage = 'Gagal mengirim email.';
    
    if (isset($mail) && !empty($mail->ErrorInfo)) {
        $errorMessage .= ' Error: ' . $mail->ErrorInfo;
    } else {
        $errorMessage .= ' Error: ' . $e->getMessage();
    }
    
    sendJsonResponse(false, $errorMessage);
} catch (\Exception $e) {
    // Catch untuk error umum PHP (fallback)
    sendJsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage());
}

?>