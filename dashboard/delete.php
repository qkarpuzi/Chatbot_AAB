<?php
include '../database/db.php';

if (isset($_GET['table']) && isset($_GET['id'])) {
    $table = $_GET['table'];
    $id = $_GET['id'];
    
    // 1. Siguria: Lejo vetëm tabela të caktuara (Whitelist)
    $allowed_tables = ['locations', 'faq', 'users', 'workouts']; // Shto emrat e tabelave tua këtu
    if (!in_array($table, $allowed_tables)) {
        die("Tabela e paautorizuar!");
    }

    try {
        $pk = getPrimaryKey($pdo, $table);

        // 2. Fshirja me Prepared Statement
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $pk = ?");
        $stmt->execute([$id]);

        // 3. Redirect me një mesazh suksesi (opsionale)
        header("Location: indexadmin.php?table=$table&message=deleted");
        exit;

    } catch (PDOException $e) {
        // Trajtimi i gabimeve (p.sh. nëse ka lidhje me tabela tjera)
        die("Gabim gjatë fshirjes: " . $e->getMessage());
    }
} else {
    die("Mungojnë parametrat për fshirje.");
}
?>