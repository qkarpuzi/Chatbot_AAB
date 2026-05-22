<?php
$host = 'aws-0-eu-west-1.pooler.supabase.com';
$port = '6543'; // Porti 6543 (PgBouncer Pooler) rrit shpejtësinë e lidhjeve drastikisht 
$db   = 'postgres';
$user = 'postgres.vvnjnnrfiamqwhateovt'; 
$pass = 'F#x6$bmA&mfMZvs';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true, // E detyrueshme për portin 6543 tek Supabase
    PDO::ATTR_PERSISTENT         => true, 
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} 
catch (\PDOException $e) {
    die("Gabim lidhje: " . $e->getMessage());
}
?>