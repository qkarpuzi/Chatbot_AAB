<?php
$host = 'aws-0-eu-west-1.pooler.supabase.com';
$port = '5432';
$db   = 'postgres';
$user = 'postgres.vvnjnnrfiamqwhateovt'; 
$pass = 'F#x6$bmA&mfMZvs';
$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;options='--endpoint=vvnjnnrfiamqwhateovt'";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    echo "<script>console.log('Database connected successfully!');</script>";
} catch (\PDOException $e) {  
    die("<script>console.log('Gabim ne lidhje: " . $e->getMessage() . "');</script>");
}
function getPrimaryKey($pdo, $table)
{
    try {
        $stmt = $pdo->prepare("
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = :table::regclass
            AND i.indisprimary
        ");
        $stmt->execute(['table' => $table]);
        $result = $stmt->fetch();
        return $result['column_name'] ?? 'id';

    } catch (Exception $e) {
        return 'id';
    }
}
?>