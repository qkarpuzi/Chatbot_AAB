<?php 
require_once 'auth.php';
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
        <a class="navbar-brand" href="indexadmin.php">🛠 ChatBot Admin Dashboard</a>
        <div>
            <a href="../index.php" class="btn btn-outline-light btn-sm me-2">← Kthehu në Chatbot</a>
            <a href="../logout.php" class="btn btn-danger btn-sm">Dilni (Logout)</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row">
        
        <!-- Sidebar -->
        <div class="col-md-2">
            <div class="list-group sidebar">
                <?php
$tables = ['admins', 'chat_messages', 'default_responses', 'directions', 'faq', 
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
                    <!-- Search Bar -->
                    <form method="GET" action="indexadmin.php" class="mb-3 d-flex">
                        <input type="hidden" name="table" value="<?php echo htmlspecialchars($current_table); ?>">
                        <input type="text" name="search" class="form-control me-2" placeholder="Kërko me fjalë, shkurtesa etj..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        <button type="submit" class="btn btn-secondary">Kërko</button>
                        <?php if(!empty($_GET['search'])): ?>
                            <a href="indexadmin.php?table=<?php echo htmlspecialchars($current_table); ?>" class="btn btn-outline-danger ms-2">Pastro</a>
                        <?php endif; ?>
                    </form>
                    
                    <?php
                    try {
                        if (!function_exists('getPrimaryKey')) {
                            function getPrimaryKey(PDO $pdo, string $table) {
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
                        
                        // Handle Search
                        $search = $_GET['search'] ?? '';
                        $whereClause = "";
                        $params = [];
                        
                        if (!empty($search)) {
                            // Fetch column names from information_schema dynamically
                            $colQ = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :tbl");
                            $colQ->execute(['tbl' => $current_table]);
                            $cols = $colQ->fetchAll(PDO::FETCH_COLUMN);
                            
                            if (count($cols) > 0) {
                                $whereParts = [];
                                foreach ($cols as $col) {
                                    $whereParts[] = "\"$col\"::text ILIKE :search";
                                }
                                $whereClause = "WHERE " . implode(" OR ", $whereParts);
                                $params['search'] = "%$search%";
                            }
                        }
                        
                        $stmt = $pdo->prepare("SELECT * FROM $current_table $whereClause ORDER BY $pk ASC");
                        $stmt->execute($params);
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
                                    echo "<td>" . nl2br($display) . "</td>";
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