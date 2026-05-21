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
// INPUT
// =======================

$message = $_POST['message'] ?? "";
$message = mb_strtolower($message, 'UTF-8');
$message = preg_replace('/[^\p{L}\p{N}\s]/u', '', $message);

// =======================
// UI
// =======================

echo "<h2>ChatBot AAB</h2>";

echo "<form method='POST'>
        <input type='text' name='message' placeholder='Shkruaj pyetjen...' required>
        <button type='submit'>Send</button>
      </form>";

if ($message == "") exit;

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
// SMART SEARCH (FIXED)
// =======================

function merrPergjigjen($pdo, $message)
{
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

        // 3. description match (SHUMË E RËNDËSISHME)
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
            "reply" =>
                "📍 Lokacioni: " . $bestMatch['name'] . "\n" .
                "🏢 Kati: " . $bestMatch['floor'] . "\n" .
                "🚪 Dhoma: " . $bestMatch['room_number'] . "\n" .
                "📝 Pershkrimi: " . $bestMatch['description'],
            "location_id" => $bestMatch['location_id']
        ];
    }

    return [
        "status" => "not_found",
        "reply" => "Nuk u gjet asnje lokacion."
    ];
}

// =======================
// RUN
// =======================

$response = merrPergjigjen($pdo, $message);

// =======================
// SAVE CHAT
// =======================

$stmt = $pdo->prepare("
    INSERT INTO chat_messages (user_question, bot_response, matched_type, matched_location_id)
    VALUES (:q, :r, 'location', :id)
");

$stmt->execute([
    ':q' => $message,
    ':r' => $response['reply'],
    ':id' => $response['location_id'] ?? null
]);

// =======================
// OUTPUT
// =======================

echo "<hr><h3>Pergjigja:</h3>";
echo nl2br($response['reply']);

?>