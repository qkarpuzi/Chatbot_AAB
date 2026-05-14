<?php
include '../database/db.php';

$table = $_GET['table'] ?? 'faq';
$id = $_GET['id'] ?? null;
$pk = getPrimaryKey($pdo, $table);

// 1. Marrja e strukturës së kolonave
$stmt = $pdo->prepare("DESCRIBE `$table` text"); // Shtuam backticks për siguri
$stmt->execute();
$columnsInfo = $stmt->fetchAll();

// 2. Nëse është Edit, marrim të dhënat ekzistuese
$existingData = [];
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
    $stmt->execute([$id]);
    $existingData = $stmt->fetch();
}

// 3. Procesimi i Formularit (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST['data'];
    
    // Pastrim i të dhënave: Heqim PK dhe kolona automatike
    if (isset($data[$pk])) unset($data[$pk]);
    if (isset($data['created_at'])) unset($data['created_at']);

    $cols = array_keys($data);
    $values = array_values($data);

    try {
        if ($id) {
            // UPDATE
            $setClause = implode("=?, ", $cols) . "=?";
            $sql = "UPDATE `$table` SET $setClause WHERE `$pk` = ?";
            $values[] = $id;
            $pdo->prepare($sql)->execute($values);
        } else {
            // INSERT
            $placeholders = implode(", ", array_fill(0, count($cols), "?"));
            $sql = "INSERT INTO `$table` (" . implode(", ", $cols) . ") VALUES ($placeholders)";
            $pdo->prepare($sql)->execute($values);
        }
        header("Location: index.php?table=$table");
        exit;
    } catch (PDOException $e) {
        $error_message = "Gabim: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Menaxhimi i <?php echo ucfirst($table); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
    <div class="container bg-white p-4 rounded shadow-sm" style="max-width: 800px;">
        <h3><?php echo $id ? 'Edito' : 'Shto'; ?> në <?php echo ucfirst($table); ?></h3>
        <hr>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($columnsInfo as $col): 
                $name = $col['Field'];
                $type = $col['Type'];
                
                // Anashkalo ID kryesore dhe fushat automatike
                if ($col['Extra'] == 'auto_increment' || $name == $pk || $name == 'created_at') continue;
                
                $val = $existingData[$name] ?? '';
            ?>
                <div class="mb-3">
                    <label class="form-label fw-bold"><?php echo strtoupper($name); ?></label>

                    <?php 
                    // --- ZGJIDHJA PËR FOREIGN KEYS (LIDHJET) ---
                    
                    if ($name == 'faq_id'): ?>
                        <select name="data[<?php echo $name; ?>]" class="form-select" required>
                            <option value="">-- Zgjidh Pyetjen (FAQ) --</option>
                            <?php
                            $faqs = $pdo->query("SELECT faq_id, question FROM faq")->fetchAll();
                            foreach ($faqs as $f) {
                                $selected = ($val == $f['faq_id']) ? 'selected' : '';
                                echo "<option value='{$f['faq_id']}' $selected>{$f['faq_id']} - {$f['question']}</option>";
                            }
                            ?>
                        </select>

                    <?php elseif ($name == 'category_id'): ?>
                        <select name="data[<?php echo $name; ?>]" class="form-select" required>
                            <option value="">-- Zgjidh Kategorinë --</option>
                            <?php
                            $cats = $pdo->query("SELECT category_id, category_name FROM faq_categories")->fetchAll();
                            foreach ($cats as $c) {
                                $selected = ($val == $c['category_id']) ? 'selected' : '';
                                echo "<option value='{$c['category_id']}' $selected>{$c['category_name']}</option>";
                            }
                            ?>
                        </select>

                    <?php elseif (strpos($type, 'text') !== false): ?>
                        <textarea name="data[<?php echo $name; ?>]" class="form-control" rows="3"><?php echo htmlspecialchars($val); ?></textarea>
                    
                    <?php else: ?>
                        <input type="text" name="data[<?php echo $name; ?>]" class="form-control" value="<?php echo htmlspecialchars($val); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-success px-4">Ruaj</button>
                <a href="index.php?table=<?php echo $table; ?>" class="btn btn-secondary">Anulo</a>
            </div>
        </form>
    </div>
</body>
</html>