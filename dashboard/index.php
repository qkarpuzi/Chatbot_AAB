<?php 

include '../database/db.php'; 
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ChatBot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar .list-group-item.active { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">🛠 ChatBot Admin Dashboard</a>
        <a href="chatbot.php" class="btn btn-outline-light btn-sm">← Kthehu në Chatbot</a>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row">
        
        <!-- Sidebar -->
        <div class="col-md-2">
            <div class="list-group sidebar">
                <?php
                $tables = ['admin', 'chat_messages', 'default_responses', 'directions', 'faq', 
                          'faq_categories', 'faq_keywords', 'keywords', 'locations'];
                
                $current_table = $_GET['table'] ?? 'locations';
                
                foreach ($tables as $t) {
                    $active = ($t == $current_table) ? 'active' : '';
                    echo "<a href='?table=$t' class='list-group-item list-group-item-action $active'>" 
                         . ucfirst(str_replace('_', ' ', $t)) . "</a>";
                }
                ?>
            </div>
        </div>

        <!-- Përmbajtja -->
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Tabela: <strong><?php echo ucfirst(str_replace('_', ' ', $current_table)); ?></strong></h5>
                    <a href="form.php?table=<?php echo $current_table; ?>" class="btn btn-primary btn-sm">
                        ➕ Shto Rekord të Ri
                    </a>
                </div>
                
                <div class="card-body">
                    <?php
                    try {
                        if (!function_exists('getPrimaryKey')) {
                            function getPrimaryKey($pdo, $table) {
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT a.attname AS column_name
                                        FROM pg_index i
                                        JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                                        WHERE i.indrelid = :table::regclass AND i.indisprimary
                                    ");
                                    $stmt->execute(['table' => $table]);
                                    $result = $stmt->fetch();
                                    return $result['column_name'] ?? 'id';
                                } catch (Exception $e) {
                                    return 'id';
                                }
                            }
                        }

                        $pk = getPrimaryKey($pdo, $current_table);
                        
                        $stmt = $pdo->query("SELECT * FROM $current_table ORDER BY $pk ASC");
                        $rows = $stmt->fetchAll();

                        if (count($rows) > 0) {
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-bordered table-hover">';
                            echo '<thead class="table-dark"><tr>';
                            
                            foreach (array_keys($rows[0]) as $col) {
                                echo "<th>" . ucfirst(str_replace('_', ' ', $col)) . "</th>";
                            }
                            echo '<th>Veprime</th></tr></thead><tbody>';

                            foreach ($rows as $row) {
                                echo "<tr>";
                                foreach ($row as $val) {
                                    $display = htmlspecialchars((string)($val ?? ''));
                                    if (strlen($display) > 60) $display = substr($display, 0, 57) . '...';
                                    echo "<td>" . $display . "</td>";
                                }

                                $id = $row[$pk] ?? '';
                                echo "<td>
                                        <a href='form.php?table=$current_table&id=$id' class='btn btn-warning btn-sm'>Edit</a>
                                        <a href='delete.php?table=$current_table&id=$id' class='btn btn-danger btn-sm' 
                                           onclick=\"return confirm('Je i sigurt që do ta fshish?')\">Fshi</a>
                                      </td>";
                                echo "</tr>";
                            }
                            echo '</tbody></table></div>';
                        } else {
                            echo "<p class='text-center text-muted p-4'>Nuk ka të dhëna në këtë tabelë.</p>";
                        }
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>Gabim: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>