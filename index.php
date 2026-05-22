<?php
// =======================
// SUPABASE CONNECTION
// =======================

$host = 'aws-0-eu-west-1.pooler.supabase.com';
$port = '5432';
$db   = 'postgres';
$user = 'postgres.vvnjnnrfiamqwhateovt';
$pass = 'F#x6$bmA&mfMZvs';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage());
}

// =======================
// NORMALIZER FUNCTION
// =======================

function normalize($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// =======================
// SMART SEARCH
// =======================

function merrPergjigjen($pdo, $message) {
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
                       "🚪 Dhoma: " . $bestMatch['room_number'] . "<br>" .
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatBot AAB Portal</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #1e1e24;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 550px;
            padding: 30px;
            background: #2a2a32;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            box-sizing: border-box;
            position: relative;
        }
        .header-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #3a3a44;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        h1 {
            font-size: 1.6rem;
            margin: 0;
            color: #ffffff;
        }
        .btn-nav-login {
            text-decoration: none;
            background-color: transparent;
            color: #a0a0a8;
            border: 1px solid #4a4a54;
            padding: 6px 14px;
            font-size: 0.85rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        .btn-nav-login:hover {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .welcome-text {
            color: #a0a0a8;
            font-size: 0.95rem;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        input[type='text'] {
            flex: 1;
            padding: 12px 16px;
            background-color: #1e1e24;
            border: 1px solid #4a4a54;
            border-radius: 6px;
            color: white;
            font-size: 0.95rem;
            outline: none;
        }
        input[type='text']:focus {
            border-color: #007bff;
        }
        button[type='submit'] {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 0 22px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        button[type='submit']:hover {
            background-color: #0056b3;
        }
        .response-box {
            background-color: #1e1e24;
            border-left: 4px solid #007bff;
            padding: 15px 20px;
            border-radius: 0 6px 6px 0;
            animation: fadeIn 0.3s ease-in-out;
        }
        .response-title {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            color: #a0a0a8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .response-content {
            font-size: 1rem;
            line-height: 1.6;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header-area">
            <h1>ChatBot AAB</h1>
            <a href="login.php" class="btn-nav-login">Kyqu në Dashboard →</a>
        </div>
        
        <p class="welcome-text">Pyetni asistentin virtual rreth lokacioneve, zyrave apo sallave brenda kolegjit.</p>
        
        <form method="POST" class="form-group">
            <input type="text" name="message" placeholder="Shkruaj pyetjen këtu (p.sh. Ku është biblioteka?)..." value="<?php echo htmlspecialchars($user_raw_message); ?>" required autocomplete="off">
            <button type="submit">Dërgo</button>
        </form>

        <?php if (!empty($bot_reply_output)): ?>
            <div class="response-box">
                <p class="response-title">Përgjigjja:</p>
                <div class="response-content">
                    <?php echo $bot_reply_output; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>