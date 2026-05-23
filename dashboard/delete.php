<?php
require_once 'auth.php';
include '../database/db.php';

// Function to get primary key dynamically
function getPrimaryKey(PDO $pdo, string $table) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_attribute a 
            ON a.attrelid = i.indrelid 
            AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = :table::regclass 
            AND i.indisprimary
        ");

        $stmt->execute(['table' => $table]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['column_name'] ?? 'id';

    } catch (Exception $e) {
        return 'id';
    }
}

if (isset($_GET['table']) && isset($_GET['id'])) {

    $table = $_GET['table'];
    $id = $_GET['id'];

    // Allowed tables
    $allowed_tables = [
        'admins',
        'chat_messages',
        'default_responses',
        'directions',
        'faq',
        'faq_categories',
        'faq_keywords',
        'keywords',
        'locations'
    ];

    if (!in_array($table, $allowed_tables)) {
        die("Tabela e paautorizuar!");
    }

    try {

        $pk = getPrimaryKey($pdo, $table);

        // DELETE query
        $sql = "DELETE FROM $table WHERE $pk = :id";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        // Redirect after delete
        header("Location: indexadmin.php?table=$table&message=deleted");
        exit;

    } catch (PDOException $e) {

        die("Gabim gjatë fshirjes: " . $e->getMessage());
    }

} else {

    die("Mungojnë parametrat për fshirje.");
}
?>