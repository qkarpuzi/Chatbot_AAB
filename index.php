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
// CHAT HISTORY / CLEAR CHAT
// =======================

if (isset($_GET['clear'])) {
    unset($_SESSION['chat_history']);
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// =======================
// NORMALIZER FUNCTION
// =======================

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
        'gjej', 'shkoj', 'tregom', 'tregome', 'kallxo',
        'kallxom', 'qka', 'cfare', 'si', 'ma', 'mi', 'nje', 'ni'
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

function krijoPergjigjeLokacioni(array $location): string
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
        strpos($nameLower, 'salle') !== false
    ) {
        $isRoom = true;
    }

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

        return $reply;
    }

    $reply = "<strong>{$name}</strong> ndodhet {$floorText} të Kolegjit AAB.";

    if ($room !== null) {
        $reply .= " Numri i zyrës ose sallës është <strong>{$room}</strong>.";
    }

    if ($description !== '') {
        $reply .= " {$description}";
    } else {
        $reply .= " Ky lokacion është pjesë e databazës së orientimit në universitet.";
    }

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
// DEFAULT FAQ ANSWERS
// =======================

function merrPergjigjeDefaultFAQ(string $message): ?array
{
    $msg = normalize($message);

    $faqList = [
        [
            'patterns' => [
                'cka eshte ky chatbot',
                'cfare eshte ky chatbot',
                'qka eshte chatbot',
                'cka eshte aab chatbot'
            ],
            'answer' => 'Ky chatbot është një asistent virtual për orientim brenda Kolegjit AAB. Ai ndihmon studentët dhe vizitorët të gjejnë salla, zyra, administratë, bibliotekë dhe lokacione të tjera.'
        ],
        [
            'patterns' => [
                'si funksionon chatboti',
                'si punon chatboti',
                'qysh funksionon chatboti',
                'si funksionon sistemi'
            ],
            'answer' => 'Chatboti analizon pyetjen tuaj, kërkon në databazën e lokacioneve dhe fjalëve kyçe, pastaj kthen përgjigjen më të përshtatshme. Nëse nuk e kupton pyetjen, ajo ruhet që administratori ta përmirësojë sistemin.'
        ],
        [
            'patterns' => [
                'per cka sherben ky sistem',
                'qka ben ky sistem',
                'pse sherben ky chatbot',
                'qellimi i chatbotit'
            ],
            'answer' => 'Ky sistem shërben për orientim në universitet. Qëllimi i tij është t’i ndihmojë përdoruesit të gjejnë më shpejt lokacionet brenda Kolegjit AAB.'
        ],
        [
            'patterns' => [
                'cka mund te pyes',
                'cfare mund te pyes',
                'qka mundem me pyet',
                'me cka mund te me ndihmosh'
            ],
            'answer' => 'Mund të më pyesni për lokacione brenda Kolegjit AAB, për shembull: “Ku është salla 108?”, “Ku gjendet administrata?”, “Ku është biblioteka?” ose “Ku mund ta marr vërtetimin?”.'
        ]
    ];

    $bestAnswer = null;
    $bestScore = 0;

    foreach ($faqList as $faq) {
        foreach ($faq['patterns'] as $pattern) {
            $score = llogaritNgjashmerine($msg, $pattern);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestAnswer = $faq['answer'];
            }
        }
    }

    if ($bestAnswer !== null && $bestScore >= 70) {
        return [
            "status" => "success",
            "matched_type" => "faq_default",
            "location_id" => null,
            "suggested_match" => null,
            "reply" => htmlspecialchars($bestAnswer)
        ];
    }

    return null;
}

// =======================
// DATABASE FAQ SEARCH
// =======================

function merrPergjigjeFAQ(PDO $pdo, string $message): ?array
{
    if (!tableExists($pdo, 'faq_questions')) {
        return merrPergjigjeDefaultFAQ($message);
    }

    try {
        $stmt = $pdo->query("
            SELECT faq_id, question, normalized_question, answer
            FROM faq_questions
            WHERE is_active = true
        ");

        $faqs = $stmt->fetchAll();

        $bestFaq = null;
        $bestScore = 0;

        foreach ($faqs as $faq) {
            $question = $faq['question'] ?? '';
            $normalizedQuestion = $faq['normalized_question'] ?? $question;

            $score1 = llogaritNgjashmerine($message, $question);
            $score2 = llogaritNgjashmerine($message, $normalizedQuestion);

            $score = max($score1, $score2);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFaq = $faq;
            }
        }

        if ($bestFaq && $bestScore >= 70) {
            return [
                "status" => "success",
                "matched_type" => "faq",
                "location_id" => null,
                "suggested_match" => $bestFaq['question'],
                "reply" => htmlspecialchars($bestFaq['answer'])
            ];
        }
    } catch (Exception $e) {
        return merrPergjigjeDefaultFAQ($message);
    }

    return merrPergjigjeDefaultFAQ($message);
}

// =======================
// LOCATION SEARCH
// =======================

function merrPergjigjeLokacion(PDO $pdo, string $message): ?array
{
    $searchMessage = normalizeForSearch($message);

    if ($searchMessage === '') {
        return null;
    }

    $bestKeywordMatch = null;
    $bestKeywordScore = 0;

    try {
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
    } catch (Exception $e) {
        $bestKeywordMatch = null;
        $bestKeywordScore = 0;
    }

    $bestLocationMatch = null;
    $bestLocationScore = 0;

    try {
        $stmt = $pdo->query("
            SELECT *
            FROM locations
            WHERE is_active = true
        ");

        $locations = $stmt->fetchAll();

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
    } catch (Exception $e) {
        $bestLocationMatch = null;
        $bestLocationScore = 0;
    }

    if ($bestKeywordMatch && $bestKeywordScore >= 80) {
        return [
            "status" => "success",
            "matched_type" => "location_keyword",
            "location_id" => $bestKeywordMatch['loc_id'],
            "suggested_match" => $bestKeywordMatch['name'],
            "reply" => krijoPergjigjeLokacioni($bestKeywordMatch)
        ];
    }

    if ($bestLocationMatch && $bestLocationScore >= 80) {
        return [
            "status" => "success",
            "matched_type" => "location",
            "location_id" => $bestLocationMatch['location_id'],
            "suggested_match" => $bestLocationMatch['name'],
            "reply" => krijoPergjigjeLokacioni($bestLocationMatch)
        ];
    }

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

    return null;
}

// =======================
// MAIN BOT LOGIC
// =======================

function merrPergjigjen(PDO $pdo, string $message): array
{
    $message = trim($message);

    if ($message === '') {
        return [
            "status" => "empty",
            "matched_type" => "empty",
            "location_id" => null,
            "suggested_match" => null,
            "reply" => "Ju lutem shkruani një pyetje."
        ];
    }

    $faqResponse = merrPergjigjeFAQ($pdo, $message);

    if ($faqResponse !== null) {
        return $faqResponse;
    }

    $locationResponse = merrPergjigjeLokacion($pdo, $message);

    if ($locationResponse !== null) {
        return $locationResponse;
    }

    return [
        "status" => "not_found",
        "matched_type" => "unresolved",
        "location_id" => null,
        "suggested_match" => null,
        "reply" => "
            Më vjen keq, nuk arrita ta kuptoj saktë pyetjen tuaj.<br><br>
            Mund të më pyesni për lokacione ose informata rreth chatbotit, për shembull:<br>
            • Ku është salla 108?<br>
            • Ku gjendet administrata?<br>
            • Ku është biblioteka?<br>
            • Çka është ky chatbot?<br>
            • Si funksionon chatboti?<br><br>
            Pyetja juaj do të ruhet për analizë, që sistemi të përmirësohet.
        "
    ];
}

// =======================
// INPUT HANDLING
// =======================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['message'] ?? ''))) {

    $user_raw_message = trim($_POST['message']);
    $response = merrPergjigjen($pdo, $user_raw_message);

    $_SESSION['chat_history'][] = [
        'role' => 'user',
        'content' => htmlspecialchars($user_raw_message),
        'time' => date('H:i')
    ];

    $_SESSION['chat_history'][] = [
        'role' => 'bot',
        'content' => $response['reply'],
        'time' => date('H:i')
    ];

    if (count($_SESSION['chat_history']) > 40) {
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -40);
    }

    $clean_msg = normalize($user_raw_message);

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
        // Chatboti vazhdon edhe nëse ruajtja dështon
    }

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
            // Chatboti vazhdon edhe nëse ruajtja dështon
        }
    }

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AAB Chatbot</title>
    <link rel="stylesheet" href="frontend/style.css">

    <style>
        body {
            color: #000;
        }

        .chat-area {
            color: #000;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .chat-top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .clear-btn {
            background: #b60000;
            color: #fff;
            text-decoration: none;
            border: none;
            padding: 9px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .clear-btn:hover {
            background: #8f0000;
        }

        .chat-body {
            padding: 22px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f2f2f2;
        }

        .welcome-box {
            background: #ffffff;
            color: #000;
            border-radius: 18px;
            padding: 22px;
            text-align: center;
            box-shadow: 0 3px 12px rgba(0,0,0,0.10);
            margin-bottom: 10px;
        }

        .welcome-box h2,
        .welcome-box p {
            color: #000;
        }

        .quick-examples {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 14px;
        }

        .quick-examples span {
            background: #f3f3f3;
            color: #000;
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.83rem;
        }

        .chat-message {
            width: 100%;
            display: flex;
            margin-bottom: 4px;
        }

        .chat-message.user {
            justify-content: flex-end;
        }

        .chat-message.bot {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 68%;
            padding: 12px 15px;
            border-radius: 18px;
            color: #000;
            font-size: 0.95rem;
            line-height: 1.55;
            box-shadow: 0 2px 7px rgba(0,0,0,0.14);
            word-wrap: break-word;
        }

        .chat-message.user .message-bubble {
            background: #e00000;
            color: #000;
            border-bottom-right-radius: 4px;
        }

        .chat-message.bot .message-bubble {
            background: #ffffff;
            color: #000;
            border-bottom-left-radius: 4px;
        }

        .message-name {
            font-size: 0.75rem;
            font-weight: 800;
            margin-bottom: 5px;
            color: #000;
            opacity: 0.85;
        }

        .message-text {
            color: #000;
            font-weight: 500;
        }

        .message-text strong {
            color: #000;
            font-weight: 800;
        }

        .message-time {
            font-size: 0.70rem;
            color: #333;
            text-align: right;
            margin-top: 6px;
            opacity: 0.75;
        }

        .input-area input {
            color: #000 !important;
            background: #fff !important;
        }

        .input-area input::placeholder {
            color: #555 !important;
        }

        @media (max-width: 768px) {
            .message-bubble {
                max-width: 88%;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .chat-top-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
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
                    <p>Pyet për salla, zyra, administratë, bibliotekë ose informata rreth chatbotit.</p>
                </div>
            </div>

            <div class="chat-top-actions">
                <a href="index.php?clear=1" class="clear-btn">Pastro</a>
            </div>
        </header>

        <section class="chat-body" id="chatBody">

            <?php if (empty($_SESSION['chat_history'])): ?>
                <div class="welcome-box">
                    <div class="bot-icon">
                        <img src="frontend/images/aab-logo (2).png" alt="AAB Logo" style="width:100%; height:100%; object-fit:cover;">
                    </div>

                    <h2>Mirë se vini në AAB Chatbot</h2>
                    <p>
                        Shkruani pyetjen tuaj për të marrë ndihmë rreth lokacioneve në universitet.
                    </p>

                    <div class="quick-examples">
                        <span>Ku është salla 108?</span>
                        <span>Ku është biblioteka?</span>
                        <span>Ku gjendet administrata?</span>
                        <span>Çka është ky chatbot?</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($_SESSION['chat_history'] as $msg): ?>
                <div class="chat-message <?php echo $msg['role'] === 'user' ? 'user' : 'bot'; ?>">
                    <div class="message-bubble">
                        <div class="message-name">
                            <?php echo $msg['role'] === 'user' ? 'Ju' : 'AAB Chatbot'; ?>
                        </div>

                        <div class="message-text">
                            <?php echo $msg['content']; ?>
                        </div>

                        <div class="message-time">
                            <?php echo htmlspecialchars($msg['time'] ?? 'Tani'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

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

<script>
    const chatBody = document.getElementById("chatBody");

    if (chatBody) {
        chatBody.scrollTop = chatBody.scrollHeight;
    }
</script>

</body>
</html>