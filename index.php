<?php
session_start();

// =======================
// SUPABASE CONNECTION
// =======================

$host = 'aws-0-eu-west-1.pooler.supabase.com';
$port = '6543'; // 6543 për t'u shmangur vonesave
$db   = 'postgres';
$user = 'postgres.vvnjnnrfiamqwhateovt';
$pass = 'F#x6$bmA&mfMZvs';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true
    ]);
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage());
}

// =======================
// NORMALIZER FUNCTION
// =======================

function normalize(string $text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// =======================
// SMART SEARCH
// =======================  

function merrPergjigjen(PDO $pdo, string $message) {
    $message = normalize($message);

    $stmt = $pdo->query("SELECT * FROM locations");
    $locations = $stmt->fetchAll();

    $bestMatch = null;
    $bestScore = 0;

    foreach ($locations as $row) {
        $name = normalize($row['name']);
        $desc = normalize($row['description']);

        $score = 0;

        // 1. direct match
        if (strpos($message, $name) !== false) {
            $score += 10;
        }

        // 2. word match
        $words = explode(" ", $name);
        foreach ($words as $w) {
            if (strlen($w) > 2 && strpos($message, $w) !== false) {
                $score += 2;
            }
        }

        // 3. description match
        $descWords = explode(" ", $desc);
        foreach ($descWords as $w) {
            if (strlen($w) > 3 && strpos($message, $w) !== false) {
                $score += 1;
            }
        }

        // 4. synonym fix
        if ($name == "biblioteka" && strpos($message, "librari") !== false) {
            $score += 8;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $row;
        }
    }

    if ($bestMatch && $bestScore >= 2) {
        return [
            "status" => "success",
            "reply" => "📍 Lokacioni: " . $bestMatch['name'] . "<br>" .
                       "🏢 Kati: " . $bestMatch['floor'] . "<br>" .
                       "🚪 Salla: " . $bestMatch['room_number'] . "<br>" .
                       "📝 Përshkrimi: " . $bestMatch['description'],
            "location_id" => $bestMatch['location_id']
        ];
    }

    return [
        "status" => "not_found",
        "reply" => "Më vjen keq, nuk u gjet asnjë lokacion për këtë kërkesë."
    ];
}

// =======================
// INPUT HANDLING & EXECUTION
// =======================

$user_raw_message = $_POST['message'] ?? "";
$bot_reply_output = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($user_raw_message))) {
    // Process matching
    $response = merrPergjigjen($pdo, $user_raw_message);
    $bot_reply_output = $response['reply'];

    // Clean for database log entry
    $clean_msg = mb_strtolower($user_raw_message, 'UTF-8');
    $clean_msg = preg_replace('/[^\p{L}\p{N}\s]/u', '', $clean_msg);

    // Save chat metrics to DB
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (user_question, bot_response, matched_type, matched_location_id)
        VALUES (:q, :r, 'location', :id)
    ");
    $stmt->execute([
        ':q'  => $clean_msg,
        ':r'  => strip_tags($response['reply']), // store raw text in db without HTML tags
        ':id' => $response['location_id'] ?? null
    ]);
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AAB Chatbot</title>
  <link rel="stylesheet" href="frontend/style.css">
</head>
<body>

<div class="app">

  <aside class="sidebar">
    <div>
      <div class="brand">
        <div class="logo-circle">
          <img src="frontend/images/aab-logo (2).png" alt="AAB Logo">
        </div>
        <div>
          <h2>AAB Chatbot</h2>
          <p>Orientim në Universitet</p>
        </div>
      </div>

      <div class="sidebar-info">
        <h3>Asistent virtual</h3>
        <p>
          Ky chatbot është krijuar për t'i ndihmuar studentët dhe vizitorët
          të gjejnë lokacionet brenda Kolegjit AAB.
        </p>
      </div>
    </div>

    <div class="bottom-menu">
      <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
        <a href="dashboard/indexadmin.php" style="text-decoration:none; color:#0d6efd; font-weight:bold;">⚙ Shko te Dashboard-i</a>
      <?php else: ?>
        <a href="login.php" style="text-decoration:none; color:inherit;">⚙ Admin Login</a>
      <?php endif; ?>
    </div>
  </aside>

  <main class="chat-area">

    <header class="header">
      <div class="header-left">
        <div class="mini-logo">
          <img src="frontend/images/aab-logo (2).png" alt="AAB Logo" style="width:100%; height:100%; object-fit:cover;">
        </div>
        <div>
          <h3>Chatbot Inteligjent për Orientim në AAB</h3>
          <p>Jam këtu për t'ju ndihmuar të gjeni çdo lokacion në universitet.</p>
        </div>
      </div>
    </header>

    <section class="chat-body">
      <div class="welcome-box">
        <div class="bot-icon">
          <img src="frontend/images/aab-logo (2).png" alt="AAB Logo" style="width:100%; height:100%; object-fit:cover;">
        </div>
        <h2>Mirë se vini në AAB Chatbot</h2>
        <p>Shkruani pyetjen tuaj për të marrë ndihmë rreth lokacioneve në universitet.</p>
      </div>

      <?php if (!empty($bot_reply_output)): ?>
      <div style="background:var(--card-bg); padding:15px; border-radius:12px; margin-bottom:15px; border:1px solid rgba(255,255,255,0.1); 
                  border-left: 4px solid var(--primary-color);">
        <p style="margin:0 0 10px 0; color:var(--text-secondary); font-size:0.85rem; text-transform:uppercase;">Përgjigja nga ChatBot:</p>
        <div style="font-size:0.95rem; line-height:1.5;">
          <?php echo $bot_reply_output; ?>
        </div>
      </div>
      <?php endif; ?>
    </section>

    <form method="POST" action="index.php" style="margin:0;">
      <footer class="input-area">
        <input type="text" name="message" value="<?php echo htmlspecialchars($user_raw_message); ?>" placeholder="Shkruani pyetjen tuaj këtu..." required autocomplete="off">
        <button type="submit" class="send">➤</button>
      </footer>
    </form>
  </main>
</div>
</body>
</html>
