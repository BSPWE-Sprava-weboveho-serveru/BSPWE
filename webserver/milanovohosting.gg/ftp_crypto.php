<?php

define('FTP_SECRET_KEY', 'zmen_tohle_na_svuj_tajny_klic_2026_nejlepe_dlouhy');
define('FTP_CIPHER', 'AES-256-CBC');

function encryptFtpPassword(string $plainPassword): string
{
    $ivLength = openssl_cipher_iv_length(FTP_CIPHER);
    $iv = random_bytes($ivLength);

    $encrypted = openssl_encrypt(
        $plainPassword,
        FTP_CIPHER,
        FTP_SECRET_KEY,
        0,
        $iv
    );

    if ($encrypted === false) {
        throw new Exception('Nepodařilo se zašifrovat FTP heslo.');
    }

    return base64_encode($iv . $encrypted);
}

function decryptFtpPassword(string $encryptedPassword): string
{
    $data = base64_decode($encryptedPassword, true);

    if ($data === false) {
        throw new Exception('Neplatný formát uloženého FTP hesla.');
    }

    $ivLength = openssl_cipher_iv_length(FTP_CIPHER);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    $decrypted = openssl_decrypt(
        $encrypted,
        FTP_CIPHER,
        FTP_SECRET_KEY,
        0,
        $iv
    );

    if ($decrypted === false) {
        throw new Exception('Nepodařilo se dešifrovat FTP heslo.');
    }

    return $decrypted;
}