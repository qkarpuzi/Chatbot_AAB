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


function normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');

    $text = str_replace(
        ['ë', 'Ë', 'ç', 'Ç'],
        ['e', 'e', 'c', 'c'],
        $text
    );

    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

// =======================
// NORMALIZER FOR SEARCH
// =======================

function normalizeForSearch(string $text): string
{
    $text = normalize($text);

    $stopWords = [
        'ku', 'eshte', 'osht', 'gjendet', 'gjindet', 'ndodhet',
        'mund', 'muj', 'me', 'ta', 'te', 'tek', 'ne', 'nga',
        'per', 'a', 'e', 'i', 'jam', 'dua', 'du', 'kerkoj',
        'gjej', 'shkoj', 'tregom', 'tregome', 'me', 'kallxo',
        'kallxom', 'qka', 'cfare'
    ];

    $words = explode(' ', $text);
    $filteredWords = [];

    foreach ($words as $word) {
        $word = trim($word);

        if ($word !== '' && !in_array($word, $stopWords)) {
            $filteredWords[] = $word;
        }
    }

    return trim(implode(' ', $filteredWords));
}

// =======================
// FUZZY SIMILARITY
// =======================

function llogaritNgjashmerine(string $text1, string $text2): int
{
    $text1 = normalizeForSearch($text1);
    $text2 = normalizeForSearch($text2);

    if ($text1 === '' || $text2 === '') {
        return 0;
    }

    $score = 0;

    if ($text1 === $text2) {
        $score += 100;
    }

    if (strpos($text1, $text2) !== false || strpos($text2, $text1) !== false) {
        $score += 70;
    }

    similar_text($text1, $text2, $percent);
    $score += (int)$percent;

    $words1 = explode(' ', $text1);
    $words2 = explode(' ', $text2);

    foreach ($words1 as $w1) {
        foreach ($words2 as $w2) {
            if (mb_strlen($w1, 'UTF-8') <= 2 || mb_strlen($w2, 'UTF-8') <= 2) {
                continue;
            }

            if ($w1 === $w2) {
                $score += 25;
            } else {
                $distance = levenshtein($w1, $w2);

                if ($distance <= 1) {
                    $score += 20;
                } elseif ($distance <= 2) {
                    $score += 15;
                } elseif ($distance <= 3 && mb_strlen($w1, 'UTF-8') >= 6) {
                    $score += 8;
                }
            }
        }
    }

    return $score;
}

// =======================
// FLOOR TEXT
// =======================

function krijoTekstinEKatit($floorRaw): string
{
    if ($floorRaw === null || $floorRaw === '') {
        return "në një kat që nuk është specifikuar ende në databazë";
    }

    $floor = trim((string)$floorRaw);

    if ($floor === '0') {
        return "në katin përdhesë";
    }

    if ($floor === '-1') {
        return "në bodrum";
    }

    return "në katin " . htmlspecialchars($floor);
}

// =======================
// NATURAL LOCATION REPLY
// =======================

function krijoPergjigjeLokacioni(array $location, string $intro): string
{
    $nameRaw = trim($location['name'] ?? 'lokacioni i kërkuar');
    $name = htmlspecialchars($nameRaw);

    $descriptionRaw = trim($location['description'] ?? '');
    $description = htmlspecialchars($descriptionRaw);

    $floorRaw = $location['floor'] ?? null;
    $roomRaw = $location['room_number'] ?? null;

    $floorText = krijoTekstinEKatit($floorRaw);

    $room = (!empty($roomRaw) && $roomRaw != '0')
        ? htmlspecialchars((string)$roomRaw)
        : null;

    $nameLower = normalize($nameRaw);

    $isRoom = false;

    if ($room !== null) {
        $isRoom = true;
    }

    if (
        strpos($nameLower, 'salla') !== false ||
        strpos($nameLower, 'salle') !== false ||
        strpos($nameLower, 'a ') === 0 ||
        strpos($nameLower, 'b ') === 0
    ) {
        $isRoom = true;
    }

    // Për salla
    if ($isRoom) {
        if ($room !== null) {
            $reply = "Salla <strong>{$room}</strong> ndodhet {$floorText} të Kolegjit AAB.";
        } else {
            $reply = "<strong>{$name}</strong> ndodhet {$floorText} të Kolegjit AAB.";
        }

        if ($description !== '') {
            $reply .= " {$description}";
        } else {
            $reply .= " Kjo sallë është e regjistruar në databazën e orientimit të universitetit.";
        }

        $reply .= "<br><br>Mund të më pyesni edhe për një sallë tjetër, për shembull: <strong>Ku është salla 108?</strong>";

        return $reply;
    }

    // Për lokacione të tjera
    $reply = "<strong>{$name}</strong> ndodhet {$floorText} të Kolegjit AAB.";

    if ($room !== null) {
        $reply .= " Numri i zyrës ose sallës është <strong>{$room}</strong>.";
    }

    if ($description !== '') {
        $reply .= " {$description}";
    } else {
        $reply .= " Ky lokacion është pjesë e databazës së orientimit në universitet.";
    }

    $reply .= "<br><br>Nëse keni nevojë, mund të më pyesni edhe për një lokacion tjetër brenda Kolegjit AAB.";

    return $reply;
}

// =======================
// SUGGESTION REPLY
// =======================

function krijoPergjigjeSugjeruese(array $location): string
{
    $nameRaw = trim($location['name'] ?? 'lokacion i panjohur');
    $name = htmlspecialchars($nameRaw);

    $floorRaw = $location['floor'] ?? null;
    $roomRaw = $location['room_number'] ?? null;

    $floorText = krijoTekstinEKatit($floorRaw);

    $room = (!empty($roomRaw) && $roomRaw != '0')
        ? htmlspecialchars((string)$roomRaw)
        : null;

    $reply = "Nuk jam plotësisht i sigurt për pyetjen tuaj, por ndoshta keni menduar për <strong>{$name}</strong>.";

    if ($room !== null) {
        $reply .= "<br><br>Ky lokacion është i regjistruar si salla/zyra <strong>{$room}</strong> dhe ndodhet {$floorText}.";
    } else {
        $reply .= "<br><br>Ky lokacion ndodhet {$floorText}.";
    }

    $reply .= "<br><br>Ju lutem shkruani pyetjen pak më qartë, për shembull: <strong>Ku është {$name}?</strong>";

    return $reply;
}

// =======================
// CHECK IF TABLE EXISTS
// =======================

function tableExists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = :table_name
            )
        ");

        $stmt->execute([
            ':table_name' => $tableName
        ]);

        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// =======================
// SMART CHATBOT SEARCH
// =======================

function merrPergjigjen(PDO $pdo, string $message): array
{
    $searchMessage = normalizeForSearch($message);

    if ($searchMessage === '') {
        return [
            "status" => "empty",
            "matched_type" => "empty",
            "location_id" => null,
            "suggested_match" => null,
            "reply" => "Ju lutem shkruani një pyetje për lokacionet në Kolegjin AAB."
        ];
    }

    // =====================================================
    // 1. SEARCH IN KEYWORDS
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
        $keyword = $row['keyword'] ?? '';
        $normalizedKeyword = $row['normalized_keyword'] ?? $keyword;

        $score1 = llogaritNgjashmerine($searchMessage, $keyword);
        $score2 = llogaritNgjashmerine($searchMessage, $normalizedKeyword);

        $score = max($score1, $score2);

        if ($score > $bestKeywordScore) {
            $bestKeywordScore = $score;
            $bestKeywordMatch = $row;
        }
    }

    if ($bestKeywordMatch && $bestKeywordScore >= 85) {
        return [
            "status" => "success",
            "matched_type" => "keyword_fuzzy",
            "location_id" => $bestKeywordMatch['loc_id'],
            "suggested_match" => $bestKeywordMatch['name'],
            "reply" => krijoPergjigjeLokacioni(
                $bestKeywordMatch,
                "Po, e kuptova pyetjen tuaj dhe e gjeta lokacionin përkatës."
            )
        ];
    }

    // =====================================================
    // 2. SEARCH IN LOCATIONS
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
        $name = $row['name'] ?? '';
        $description = $row['description'] ?? '';
        $roomNumber = $row['room_number'] ?? '';

        $scoreName = llogaritNgjashmerine($searchMessage, $name);
        $scoreDesc = llogaritNgjashmerine($searchMessage, $description);
        $scoreRoom = 0;

        if (!empty($roomNumber) && $roomNumber != '0') {
            $scoreRoom = llogaritNgjashmerine($searchMessage, $roomNumber);
        }

        $score = max($scoreName, $scoreRoom, (int)($scoreDesc / 2));

        if ($score > $bestLocationScore) {
            $bestLocationScore = $score;
            $bestLocationMatch = $row;
        }
    }

    if ($bestLocationMatch && $bestLocationScore >= 85) {
        return [
            "status" => "success",
            "matched_type" => "location_fuzzy",
            "location_id" => $bestLocationMatch['location_id'],
            "suggested_match" => $bestLocationMatch['name'],
            "reply" => krijoPergjigjeLokacioni(
                $bestLocationMatch,
                "E gjeta lokacionin që po kërkoni në databazën e Kolegjit AAB."
            )
        ];
    }

    // =====================================================
    // 3. SEARCH IN TRAINING EXAMPLES
    // =====================================================

    if (tableExists($pdo, 'training_examples')) {
        $stmt = $pdo->query("
            SELECT 
                t.example_id,
                t.user_question,
                t.normalized_question,
                t.answer_type,
                t.location_id,

                l.location_id AS loc_id,
                l.name,
                l.description,
                l.floor,
                l.room_number
            FROM training_examples t
            LEFT JOIN locations l ON t.location_id = l.location_id
            WHERE t.approved = true
        ");

        $examples = $stmt->fetchAll();

        $bestExample = null;
        $bestExampleScore = 0;

        foreach ($examples as $row) {
            $q1 = $row['user_question'] ?? '';
            $q2 = $row['normalized_question'] ?? $q1;

            $score1 = llogaritNgjashmerine($searchMessage, $q1);
            $score2 = llogaritNgjashmerine($searchMessage, $q2);

            $score = max($score1, $score2);

            if ($score > $bestExampleScore) {
                $bestExampleScore = $score;
                $bestExample = $row;
            }
        }

        if ($bestExample && $bestExampleScore >= 80 && !empty($bestExample['loc_id'])) {
            return [
                "status" => "success",
                "matched_type" => "training_example",
                "location_id" => $bestExample['loc_id'],
                "suggested_match" => $bestExample['name'],
                "reply" => krijoPergjigjeLokacioni(
                    $bestExample,
                    "E kuptova pyetjen nga shembujt e trajnuar më parë."
                )
            ];
        }
    }

    // =====================================================
    // 4. MEDIUM SCORE - SUGGESTION
    // =====================================================

    if ($bestKeywordMatch && $bestKeywordScore >= 55) {
        return [
            "status" => "suggestion",
            "matched_type" => "suggestion",
            "location_id" => $bestKeywordMatch['loc_id'],
            "suggested_match" => $bestKeywordMatch['name'],
            "reply" => krijoPergjigjeSugjeruese($bestKeywordMatch)
        ];
    }

    if ($bestLocationMatch && $bestLocationScore >= 55) {
        return [
            "status" => "suggestion",
            "matched_type" => "suggestion",
            "location_id" => $bestLocationMatch['location_id'],
            "suggested_match" => $bestLocationMatch['name'],
            "reply" => krijoPergjigjeSugjeruese($bestLocationMatch)
        ];
    }

    // =====================================================
    // 5. NOT FOUND
    // =====================================================

    return [
        "status" => "not_found",
        "matched_type" => "unresolved",
        "location_id" => null,
        "suggested_match" => null,
        "reply" => "
            Më vjen keq, nuk arrita ta kuptoj saktë pyetjen tuaj.<br><br>
            Mund ta shkruani pyetjen në një formë më të qartë, për shembull:<br>
            • Ku është biblioteka?<br>
            • Ku gjendet administrata?<br>
            • Ku është salla 108?<br>
            • Ku mund ta marr vërtetimin?<br><br>
            Pyetja juaj do të ruhet për analizë, në mënyrë që chatboti të përmirësohet në të ardhmen.
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

    try {
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
    } catch (Exception $e) {
        // Nuk e ndalim chatbotin nëse ruajtja e bisedës dështon
    }

    // =====================================================
    // SAVE UNRESOLVED OR SUGGESTION QUESTION
    // =====================================================

    if ($response['status'] === 'not_found' || $response['status'] === 'suggestion') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO unresolved_questions
                (user_question, normalized_question, suggested_match, status)
                VALUES
                (:user_question, :normalized_question, :suggested_match, :status)
            ");

            $stmt->execute([
                ':user_question' => $user_raw_message,
                ':normalized_question' => $clean_msg,
                ':suggested_match' => $response['suggested_match'],
                ':status' => $response['status'] === 'suggestion' ? 'suggested' : 'pending'
            ]);
        } catch (Exception $e) {
            // Nuk e ndalim chatbotin nëse ruajtja e pyetjes dështon
        }
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