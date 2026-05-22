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

function normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');

    // Heq shenjat e pikësimit, por i ruan shkronjat shqipe
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

    // Heq hapësirat e tepërta
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

// =======================
// PROFESSIONAL REPLY FORMAT
// =======================

function krijoPergjigjeLokacioni(array $location, string $intro): string
{
    $name = htmlspecialchars($location['name'] ?? 'I panjohur');
    $description = htmlspecialchars($location['description'] ?? 'Nuk ka përshkrim.');
    $floor = htmlspecialchars((string)($location['floor'] ?? 'Nuk është specifikuar'));
    $room = !empty($location['room_number'])
        ? htmlspecialchars($location['room_number'])
        : 'Nuk është specifikuar';

    return "
        {$intro}<br><br>
        📍 <strong>Lokacioni:</strong> {$name}<br>
        🏢 <strong>Kati:</strong> {$floor}<br>
        🚪 <strong>Salla/Zyra:</strong> {$room}<br>
        📝 <strong>Përshkrimi:</strong> {$description}<br><br>
        Nëse dëshironi, mund të më pyesni edhe për një lokacion tjetër brenda Kolegjit AAB.
    ";
}

// =======================
// SMART CHATBOT SEARCH
// =======================

function merrPergjigjen(PDO $pdo, string $message): array
{
    $message = normalize($message);

    if ($message === '') {
        return [
            "status" => "empty",
            "matched_type" => "empty",
            "location_id" => null,
            "reply" => "Ju lutem shkruani një pyetje për lokacionet në Kolegjin AAB."
        ];
    }

    // =====================================================
    // 1. SEARCH FIRST IN KEYWORDS TABLE
    // =====================================================

    $stmt = $pdo->query("
        SELECT 
            k.keyword_id,
            k.keyword,
            k.normalized_keyword,
            k.intent_type,
            k.location_id,

            l.location_id AS loc_id,
            l.name,
            l.description,
            l.floor,
            l.room_number,
            l.x_coord,
            l.y_coord,
            l.is_active
        FROM keywords k
        INNER JOIN locations l ON k.location_id = l.location_id
        WHERE l.is_active = true
    ");

    $keywords = $stmt->fetchAll();

    $bestKeywordMatch = null;
    $bestKeywordScore = 0;

    foreach ($keywords as $row) {
        $keyword = normalize($row['keyword'] ?? '');
        $normalizedKeyword = normalize($row['normalized_keyword'] ?? $row['keyword'] ?? '');

        $score = 0;

        // Nëse pyetja është krejt e njëjtë me keyword
        if ($message === $keyword || $message === $normalizedKeyword) {
            $score += 30;
        }

        // Nëse keyword gjendet brenda pyetjes
        if ($keyword !== '' && strpos($message, $keyword) !== false) {
            $score += 20;
        }

        if ($normalizedKeyword !== '' && strpos($message, $normalizedKeyword) !== false) {
            $score += 20;
        }

        // Krahasim fjalë për fjalë
        $keywordWords = explode(" ", $keyword);

        foreach ($keywordWords as $word) {
            if (mb_strlen($word, 'UTF-8') > 2 && strpos($message, $word) !== false) {
                $score += 4;
            }
        }

        // Disa fjalë orientuese që përdoruesi mund t'i shkruajë
        $commonWords = [
            'ku',
            'eshte',
            'gjendet',
            'ndodhet',
            'salla',
            'zyra',
            'kati',
            'dekanati',
            'administrata',
            'biblioteka',
            'pagesa',
            'dokumente',
            'vertetim'
        ];

        foreach ($commonWords as $word) {
            if (strpos($message, $word) !== false && strpos($keyword, $word) !== false) {
                $score += 2;
            }
        }

        if ($score > $bestKeywordScore) {
            $bestKeywordScore = $score;
            $bestKeywordMatch = $row;
        }
    }

    if ($bestKeywordMatch && $bestKeywordScore >= 6) {
        return [
            "status" => "success",
            "matched_type" => "keyword",
            "location_id" => $bestKeywordMatch['loc_id'],
            "reply" => krijoPergjigjeLokacioni(
                $bestKeywordMatch,
                "Po, e gjeta lokacionin që lidhet me pyetjen tuaj."
            )
        ];
    }

    // =====================================================
    // 2. FALLBACK SEARCH IN LOCATIONS TABLE
    // =====================================================

    $stmt = $pdo->query("
        SELECT *
        FROM locations
        WHERE is_active = true
    ");

    $locations = $stmt->fetchAll();

    $bestLocationMatch = null;
    $bestLocationScore = 0;

    foreach ($locations as $row) {
        $name = normalize($row['name'] ?? '');
        $description = normalize($row['description'] ?? '');
        $roomNumber = normalize($row['room_number'] ?? '');

        $score = 0;

        // Nëse emri i lokacionit gjendet direkt në pyetje
        if ($name !== '' && strpos($message, $name) !== false) {
            $score += 20;
        }

        // Nëse numri i sallës gjendet në pyetje
        if ($roomNumber !== '' && $roomNumber !== '0' && strpos($message, $roomNumber) !== false) {
            $score += 20;
        }

        // Krahasim sipas fjalëve të emrit
        $nameWords = explode(" ", $name);

        foreach ($nameWords as $word) {
            if (mb_strlen($word, 'UTF-8') > 2 && strpos($message, $word) !== false) {
                $score += 5;
            }
        }

        // Krahasim me përshkrimin
        $descriptionWords = explode(" ", $description);

        foreach ($descriptionWords as $word) {
            if (mb_strlen($word, 'UTF-8') > 4 && strpos($message, $word) !== false) {
                $score += 1;
            }
        }

        if ($score > $bestLocationScore) {
            $bestLocationScore = $score;
            $bestLocationMatch = $row;
        }
    }

    if ($bestLocationMatch && $bestLocationScore >= 5) {
        return [
            "status" => "success",
            "matched_type" => "location",
            "location_id" => $bestLocationMatch['location_id'],
            "reply" => krijoPergjigjeLokacioni(
                $bestLocationMatch,
                "E gjeta këtë lokacion në databazën e Kolegjit AAB."
            )
        ];
    }

    // =====================================================
    // 3. IF NOTHING FOUND
    // =====================================================

    return [
        "status" => "not_found",
        "matched_type" => "unresolved",
        "location_id" => null,
        "reply" => "
            Më vjen keq, nuk arrita ta gjej saktë lokacionin që kërkuat.<br><br>
            Ju lutem provoni ta shkruani pyetjen më qartë, për shembull:<br>
            • Ku është biblioteka?<br>
            • Ku gjendet administrata?<br>
            • Ku është salla A-01?<br>
            • Ku mund ta marr vërtetimin?<br><br>
            Kjo pyetje do të ruhet për t'u analizuar nga administratori dhe për ta përmirësuar chatbotin.
        "
    ];
}

// =======================
// INPUT HANDLING
// =======================

$user_raw_message = $_POST['message'] ?? "";
$bot_reply_output = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($user_raw_message))) {

    $response = merrPergjigjen($pdo, $user_raw_message);
    $bot_reply_output = $response['reply'];

    $clean_msg = normalize($user_raw_message);

    // =====================================================
    // SAVE CHAT MESSAGE
    // =====================================================

    $stmt = $pdo->prepare("
        INSERT INTO chat_messages 
        (user_question, bot_response, matched_type, matched_location_id)
        VALUES 
        (:user_question, :bot_response, :matched_type, :matched_location_id)
    ");

    $stmt->execute([
        ':user_question' => $clean_msg,
        ':bot_response' => strip_tags($response['reply']),
        ':matched_type' => $response['matched_type'],
        ':matched_location_id' => $response['location_id']
    ]);

    // =====================================================
    // SAVE UNRESOLVED QUESTION IF BOT DID NOT FIND ANSWER
    // =====================================================

    if ($response['status'] === 'not_found') {
        $stmt = $pdo->prepare("
            INSERT INTO unresolved_questions
            (user_question, normalized_question, suggested_match, status)
            VALUES
            (:user_question, :normalized_question, :suggested_match, :status)
        ");

        $stmt->execute([
            ':user_question' => $user_raw_message,
            ':normalized_question' => $clean_msg,
            ':suggested_match' => null,
            ':status' => 'pending'
        ]);
    }
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
                <a href="dashboard/indexadmin.php" style="text-decoration:none; color:#0d6efd; font-weight:bold;">
                    ⚙ Shko te Dashboard-i
                </a>
            <?php else: ?>
                <a href="login.php" style="text-decoration:none; color:inherit;">
                    ⚙ Admin Login
                </a>
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

            <?php if (!empty($user_raw_message)): ?>
                <div style="
                    background:rgba(255,255,255,0.04);
                    padding:15px;
                    border-radius:12px;
                    margin-bottom:15px;
                    border:1px solid rgba(255,255,255,0.08);
                    text-align:right;
                ">
                    <p style="
                        margin:0 0 8px 0;
                        color:var(--text-secondary);
                        font-size:0.82rem;
                        text-transform:uppercase;
                    ">
                        Pyetja juaj:
                    </p>

                    <div style="font-size:0.95rem; line-height:1.5;">
                        <?php echo htmlspecialchars($user_raw_message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($bot_reply_output)): ?>
                <div style="
                    background:var(--card-bg);
                    padding:15px;
                    border-radius:12px;
                    margin-bottom:15px;
                    border:1px solid rgba(255,255,255,0.1);
                    border-left:4px solid var(--primary-color);
                ">
                    <p style="
                        margin:0 0 10px 0;
                        color:var(--text-secondary);
                        font-size:0.85rem;
                        text-transform:uppercase;
                    ">
                        Përgjigja nga Chatbot:
                    </p>

                    <div style="font-size:0.95rem; line-height:1.6;">
                        <?php echo $bot_reply_output; ?>
                    </div>
                </div>
            <?php endif; ?>

        </section>

        <form method="POST" action="index.php" style="margin:0;">
            <footer class="input-area">
                <input 
                    type="text" 
                    name="message" 
                    value="" 
                    placeholder="Shkruani pyetjen tuaj këtu..." 
                    required 
                    autocomplete="off"
                >

                <button type="submit" class="send">➤</button>
            </footer>
        </form>

    </main>

</div>

</body>
</html>