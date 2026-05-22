<?php
include '../database/db.php';

$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? null;

if (empty($table)) {
    die("❌ Tabela nuk është specifikuar.");
}
try {
    $pk_query = "
        SELECT kcu.column_name 
        FROM information_schema.table_constraints tc 
        JOIN information_schema.key_column_usage kcu 
          ON tc.constraint_name = kcu.constraint_name
          AND tc.table_schema = kcu.table_schema
        WHERE tc.constraint_type = 'PRIMARY KEY' 
          AND tc.table_name = :table
          AND tc.table_schema = 'public'
        LIMIT 1
    ";
    $pk_stmt = $pdo->prepare($pk_query);
    $pk_stmt->execute(['table' => $table]);
    $pk_result = $pk_stmt->fetch();
    $pk = $pk_result ? $pk_result['column_name'] : 'id';

} catch (Exception $e) {
    $pk = 'id'; 
}


try {
    $stmt = $pdo->prepare("
        SELECT 
            column_name AS \"Field\", 
            data_type AS \"Type\", 
            is_nullable AS \"Null\",
            column_default AS \"Default\"
        FROM information_schema.columns 
        WHERE table_name = :table 
        AND table_schema = 'public'
        ORDER BY ordinal_position
    ");
    $stmt->execute(['table' => $table]);
    $columnsInfo = $stmt->fetchAll();

    if (!$columnsInfo) {
        die("❌ Tabela '$table' nuk u gjet ose nuk ka kolona.");
    }
} catch (Exception $e) {
    die("❌ Gabim gjatë marrjes së strukturës: " . $e->getMessage());
}


$existingData = [];
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $pk = ?");
    $stmt->execute([$id]);
    $existingData = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST['data'] ?? [];
    
    if (isset($data[$pk])) unset($data[$pk]); 
    if (isset($data['created_at'])) unset($data['created_at']);

    foreach ($data as $key => $value) {
        if ($value === '') {
            $data[$key] = null; 
        }
    }

    $columns = array_keys($data);
    $values = array_values($data);

    try {
        if ($id) {
            // UPDATE
            $setClause = implode(", ", array_map(function($col) { return "$col = ?"; }, $columns));
            $sql = "UPDATE $table SET $setClause WHERE $pk = ?";
            $values[] = $id; 
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        } else {
            // INSERT
            $colsString = implode(", ", $columns);
            $placeholders = implode(", ", array_fill(0, count($columns), "?"));
            $sql = "INSERT INTO $table ($colsString) VALUES ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }
        
        header("Location: indexadmin.php?table=$table");
        exit;
    } catch (PDOException $e) {
        die("❌ Gabim gjatë ruajtjes në databazë: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? 'Edito' : 'Shto'; ?> Rekord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">

<div class="container">
    <div class="card shadow">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0 text-center"><?php echo $id ? 'Edito Rekordin' : 'Shto Rekord të Ri'; ?></h4>
            <p class="mb-0 text-center opacity-75">Tabela: <?php echo htmlspecialchars($table); ?></p>
        </div>
        <div class="card-body p-4">
            
            <form method="POST" action="">
                
                <?php foreach ($columnsInfo as $col): 
                    $colName = $col['Field'];
                    $colType = $col['Type'];
                    
                    if ($colName == $pk || $colName == 'created_at') continue;

                    $val = $existingData[$colName] ?? '';
                ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo str_replace('_', ' ', ucfirst($colName)); ?></label>
                        
                        <?php if ($colName == 'faq_id'): ?>
                            <?php 
                                $fStmt = $pdo->query("SELECT faq_id, question FROM faq ORDER BY faq_id");
                                $faqs = $fStmt->fetchAll();
                            ?>
                            <select name="data[faq_id]" class="form-select">
                                <option value="">-- Zgjidh një pyetje --</option>
                                <?php foreach ($faqs as $f): ?>
                                    <option value="<?php echo $f['faq_id']; ?>" <?php echo ($val == $f['faq_id']) ? 'selected' : ''; ?>>
                                        ID: <?php echo $f['faq_id']; ?> - <?php echo htmlspecialchars(substr($f['question'], 0, 50)); ?>...
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($colType == 'boolean'): ?>
                            <select name="data[<?php echo $colName; ?>]" class="form-select">
                                <option value="">-- Pa përcaktuar --</option>
                                <option value="true" <?php echo ($val === true || $val === 't' || $val === 1 || $val === 'true') ? 'selected' : ''; ?>>Po (True)</option>
                                <option value="false" <?php echo ($val === false || $val === 'f' || $val === 0 || $val === 'false') ? 'selected' : ''; ?>>Jo (False)</option>
                            </select>

                        <?php elseif (strpos($colType, 'text') !== false || ($colName == 'answer') || ($colName == 'response')): ?>
                            <textarea name="data[<?php echo $colName; ?>]" class="form-control" rows="3"><?php echo htmlspecialchars($val); ?></textarea>

                        <?php else: ?>
                            <?php 
                                $inputType = 'text';
                                if (strpos($colType, 'int') !== false || strpos($colType, 'num') !== false) $inputType = 'number';
                                if (strpos($colName, 'date') !== false) $inputType = 'date';
                                if (strpos($colName, 'password') !== false) $inputType = 'password';
                            ?>
                            <input type="<?php echo $inputType; ?>" step="any" name="data[<?php echo $colName; ?>]" 
                                   class="form-control" value="<?php echo htmlspecialchars($val); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="mt-4 d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">Ruaj të dhënat</button>
                    <a href="indexadmin.php?table=<?php echo $table; ?>" class="btn btn-light border">Anulo</a>
                </div>
            </form>
            
        </div>
    </div>
</div>

</body>
</html>