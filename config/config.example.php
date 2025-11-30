<?php
/**
 * =======================================================================
 * FILE: config/config.example.php
 * DESCRIPTION: Template konfigurasi SMTP Email
 * INSTRUKSI: Copy file ini menjadi config.php dan isi dengan kredensial Anda
 * =======================================================================
 */

return [
    'smtp' => [
        'host'       => 'smtp. ... .com', // Ganti dengan SMTP host Anda
        'port'       => 465,                   // Port SMTP (465 untuk SSL, 587 untuk TLS)
        'secure'     => 'ssl',                 // 'ssl' atau 'tls'
        'auth'       => true,
        'username'   => 'your-email@domain.com', // Ganti dengan email SMTP Anda
        'password'   => 'your-password',          // Ganti dengan password email Anda
        'from_email' => 'your-email@domain.com',   // Email pengirim (biasanya sama dengan username)
        'from_name'  => 'Admin Web Notifikasi',   // Nama pengirim
    ],
    
    // Opsi tambahan
    'options' => [
        'debug' => false, // Set true untuk debugging (0-4)
    ]
];

