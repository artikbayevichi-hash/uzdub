<?php
// Bazaga ulanish sozlamalari
$DB_HOST = "localhost";
$DB_NAME = "uzdub";
$DB_USER = "root";
$DB_PASS = "";

// ---- PDO ulanish ($pdo) — saytning barcha sahifalari va AI chat shuni ishlatadi ----
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Bazaga ulanishda xatolik: " . $e->getMessage() . "<br>Iltimos, phpMyAdmin orqali 'uzdub' bazasini database.sql fayli bilan import qilganingizga ishonch hosil qiling.");
}