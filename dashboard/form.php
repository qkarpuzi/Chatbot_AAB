<?php
include '../database/db.php';

$table = $_GET['table'];
$id = $_GET['id'] ?? null;
$pk = getPrimaryKey($pdo, $table);

// Marrja e kolonave të tabelës
$stmt = $pdo->query("DESCRIBE $table");
$columnsInfo = $stmt->fetchAll();

// Nëse është Edit, marrim të dhënat ekzistuese
$existingData = [];
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $pk = ?");
    $stmt->execute([$id]);
    $existingData = $stmt->fetch();
}

// Nëse forma është dërguar (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST['data'];
    
    // Fshijmë kolonat që nuk mund të ndryshohen manualisht
    if (isset($data[$pk])) unset($data[$pk]); 
    if (isset($data['created_at'])) unset($data['created_at']);

    $columns = array_keys($data);
    $values = array_values($data);

    if ($id) {
        // Logjika për UPDATE (Edit)
        $setClause = implode(", ", array_map(function($col) { return "$col = ?"; }, $columns));
        $sql = "UPDATE $table SET $setClause WHERE $pk = ?";
        $values[] = $id; // Shtojmë ID në fund për parametrin WHERE
        $pdo->prepare($sql)->execute($values);
    } else {
        // Logjika për INSERT (Shto)
        $colsString = implode(", ", $columns);
        $placeholders = implode(", ", array_fill(0, count($columns), "?"));
        $sql = "INSERT INTO $table ($colsString) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($values);
    }

    header("Location: index.php?table=$table");
    exit;
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edito' : 'Shto'; ?> - <?php echo ucfirst($table); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
    <div class="container max-w-md bg-white p-4 rounded shadow">
        <h3><?php echo $id ? 'Edito Rekordin' : 'Shto Rekord të Ri'; ?> në: <span class="text-primary"><?php echo $table; ?></span></h3>
        <hr>
        <form method="POST">
            <?php foreach ($columnsInfo as $col): 
                $colName = $col['Field'];
                $colType = $col['Type'];
                
                // Anashkalojmë Primary Key (auto-increment) dhe datën e krijimit
                if ($col['Extra'] == 'auto_increment' || $colName == $pk) continue;
                if ($colName == 'created_at') continue;

                $val = $existingData[$colName] ?? '';
                
                // Ndërtojmë llojin e inputit bazuar në databazë
                $inputType = 'text';
                if (strpos($colType, 'int') !== false || strpos($colType, 'decimal') !== false) $inputType = 'number';
                if (strpos($colName, 'password') !== false) $inputType = 'password';
            ?>
                <div class="mb-3">
                    <label class="form-label fw-bold"><?php echo $colName; ?></label>
                    <?php if (strpos($colType, 'text') !== false): ?>
                        <textarea name="data[<?php echo $colName; ?>]" class="form-control" rows="3"><?php echo htmlspecialchars($val); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo $inputType; ?>" step="any" name="data[<?php echo $colName; ?>]" class="form-control" value="<?php echo htmlspecialchars($val); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-success"><?php echo $id ? 'Ruaj Ndryshimet' : 'Shto Rekordin'; ?></button>
            <a href="index.php?table=<?php echo $table; ?>" class="btn btn-secondary">Kthehu</a>
        </form>
    </div>
</body>
</html>