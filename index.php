<?php
session_start();
 
// Inicializimi i historisë së chat-it nëse nuk ekziston
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}
 
// Logjika për butonin "Pastro"
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    $_SESSION['chat_history'] = [];
    header("Location: index.php");
    exit();
}
 
// =======================
// SUPABASE CONNECTION
// =======================
 
$host = 'aws-0-eu-west-1.pooler.supabase.com';
$port = '6543'; 
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
// NORMALIZERS
// =======================
 
function normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}
 
function normalizeForSearch(string $text): string
{
    $text = normalize($text);
 
    $stopWords = [
        'ku', 'eshte', 'osht', 'gjendet', 'gjindet', 'ndodhet',
        'mund', 'muj', 'me', 'ta', 'te', 'tek', 'ne', 'nga',
        'per', 'a', 'e', 'i', 'jam', 'dua', 'du', 'kerkoj',
        'gjej', 'shkoj', 'tregom', 'tregome', 'kallxo',
        'kallxom', 'qka', 'cfare', 'salla', 'salle', 'zyra', 'zyre'
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
// INTENT DETECTION
// =======================
 
/**
 * Detects the intent type from the raw message.
 * Returns: 'location', 'faq', or 'unknown'
 */
function detectIntent(string $message): string
{
    $msg = mb_strtolower($message, 'UTF-8');
 
    // FAQ / informational intent triggers (Albanian + English)
    $faqTriggers = [
        // How
        'si mund', 'si mund te', 'si behet', 'si funksionon', 'si',
        // What / Which
        'cka eshte', 'çka është', 'cfare eshte', 'çfarë është',
        'cka jane', 'çka janë', 'cilat', 'cili', 'cila',
        'what is', 'what are', 'which',
        // When
        'kur', 'kur eshte', 'kur fillon', 'kur mbaron',
        'when', 'when is', 'when does',
        // Can / Do
        'a mund', 'a ka', 'a ofron', 'a ekziston',
        'can i', 'do you', 'does aab', 'is there',
        // Why
        'pse', 'why',
        // How much / How many
        'sa kushton', 'sa eshte', 'sa jane', 'sa',
        'how much', 'how many',
        // Inform / Tell me
        'me trego', 'me thuaj', 'tregomë', 'thuajmi',
        'tell me', 'explain',
        // Register / Apply / Contact
        'regjistrohem', 'aplikoj', 'kontaktoj', 'marr vertetim',
        'ndryshoj fjalekalimin', 'shoh notat', 'gjej orarin',
    ];
 
    // Location intent triggers
    $locationTriggers = [
        'ku eshte', 'ku ndodhet', 'ku gjendet', 'ku gjindet',
        'ku ka', 'ku ndodhen',
        'where is', 'where can i find',
        'salla', 'salle', 'zyra', 'zyre', 'dhoma',
        'kati', 'bodrum', 'perdhe',
    ];
 
    foreach ($locationTriggers as $trigger) {
        if (mb_strpos($msg, $trigger) !== false) {
            return 'location';
        }
    }
 
    foreach ($faqTriggers as $trigger) {
        if (mb_strpos($msg, $trigger) !== false) {
            return 'faq';
        }
    }
 
    // If the message contains a number, likely a location (room number)
    if (preg_match('/\d+/', $message)) {
        return 'location';
    }
 
    return 'unknown'; // will try both
}
 
// =======================
// FUZZY SIMILARITY
// =======================
 
function llogaritNgjashmerine(string $text1, string $text2): int
{
    $text1 = normalizeForSearch($text1);
    $text2 = normalizeForSearch($text2);
 
    if ($text1 === '' || $text2 === '') return 0;
    if ($text1 === $text2) return 100;
 
    $score = 0;
    similar_text($text1, $text2, $percent);
    $score += (int)$percent;
 
    $words1 = explode(' ', $text1);
    $words2 = explode(' ', $text2);
 
    foreach ($words1 as $w1) {
        foreach ($words2 as $w2) {
            if (mb_strlen($w1, 'UTF-8') <= 2 || mb_strlen($w2, 'UTF-8') <= 2) {
                if (is_numeric($w1) && is_numeric($w2) && $w1 !== $w2) continue;
            }
            if ($w1 === $w2) {
                $score += 40;
            } else {
                if (is_numeric($w1) || is_numeric($w2)) continue;
                $distance = levenshtein($w1, $w2);
                if ($distance <= 1) $score += 20;
                elseif ($distance <= 2) $score += 15;
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
    if ($floor === '0') return "në katin përdhesë";
    if ($floor === '-1') return "në bodrum";
    return "në katin " . htmlspecialchars($floor);
}
 
// =======================
// LOCATION REPLY BUILDERS
// =======================
 
function krijoPergjigjeLokacioni(array $location, string $intro): string
{
    $nameRaw = trim($location['name'] ?? 'lokacioni i kërkuar');
    $name = htmlspecialchars($nameRaw);
    $description = htmlspecialchars(trim($location['description'] ?? ''));
    $floorText = krijoTekstinEKatit($location['floor'] ?? null);
    $roomRaw = $location['name'] ?? null;
    $room = (!empty($roomRaw) && $roomRaw != '0') ? htmlspecialchars((string)$roomRaw) : null;
 
    if ($room !== null) {
        $reply = "Përshendetje, <strong>{$room}</strong> ({$name}) ndodhet {$floorText} të Kolegjit AAB.";
    } else {
        $reply = "<strong>{$name}</strong> ndodhet {$floorText} të Kolegjit AAB.";
    }
    if ($description !== '') $reply .= " " . $description;
    $reply .= "<br><br>Mund të më pyesni ndonjë gjë tjetër?";
    return $reply;
}
 
function krijoPergjigjeSugjeruese(array $location): string
{
    $name = htmlspecialchars(trim($location['name'] ?? 'lokacion i panjohur'));
    $floorText = krijoTekstinEKatit($location['floor'] ?? null);
    $roomRaw = $location['name'] ?? null;
    $room = (!empty($roomRaw) && $roomRaw != '0') ? htmlspecialchars((string)$roomRaw) : null;
 
    $reply = "Nuk jam plotësisht i sigurt, por ndoshta keni menduar për <strong>{$name}</strong>.";
    if ($room !== null) {
        $reply .= "<br>Ky lokacion është i regjistruar si salla/zyra <strong>{$room}</strong> dhe ndodhet {$floorText}.";
    } else {
        $reply .= "<br>Ndodhet {$floorText}.";
    }
    return $reply;
}
 
// =======================
// FAQ SEARCH
// =======================
 
/**
 * Searches faq_questions and faq_keywords tables for the best matching answer.
 * Returns array with status and reply, or null if no good match found.
 */
function searchFAQ(PDO $pdo, string $message): ?array
{
    $searchMsg = normalizeForSearch($message);
    if ($searchMsg === '') return null;
 
    $bestScore = 0;
    $bestFAQ = null;
 
    // --- Step 1: Search faq_questions (question + normalized_question) ---
    try {
        $stmt = $pdo->query("SELECT * FROM faq_questions WHERE is_active = true");
        $faqQuestions = $stmt->fetchAll();
 
        foreach ($faqQuestions as $row) {
            $scoreQ  = llogaritNgjashmerine($searchMsg, $row['question'] ?? '');
            $scoreNQ = llogaritNgjashmerine($searchMsg, $row['normalized_question'] ?? '');
            $score   = max($scoreQ, $scoreNQ);
 
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFAQ   = $row;
            }
        }
    } catch (Exception $e) {}
 
    // --- Step 2: Search faq_keywords and load matching faq_question answers ---
    try {
        $stmt = $pdo->query("SELECT fk.keyword, fq.faq_id, fq.answer, fq.question, fq.normalized_question
                             FROM faq_keywords fk
                             INNER JOIN faq_questions fq ON fk.faq_id = fq.faq_id
                             WHERE fq.is_active = true");
        $faqKeywords = $stmt->fetchAll();
 
        foreach ($faqKeywords as $row) {
            $score = llogaritNgjashmerine($searchMsg, $row['keyword'] ?? '');
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFAQ   = $row;
            }
        }
    } catch (Exception $e) {}
 
    // --- Step 3: Also check legacy faq table ---
    try {
        $stmt = $pdo->query("SELECT * FROM faq WHERE is_active = true");
        $legacyFAQs = $stmt->fetchAll();
 
        foreach ($legacyFAQs as $row) {
            // Check against faq_keywords linked to this faq_id
            $scoreQ = llogaritNgjashmerine($searchMsg, $row['question'] ?? '');
            if ($scoreQ > $bestScore) {
                $bestScore = $scoreQ;
                // Map legacy faq format to faq_questions format
                $bestFAQ = [
                    'question'            => $row['question'],
                    'normalized_question' => normalize($row['question']),
                    'answer'              => $row['answer'],
                ];
            }
 
            // Also match against faq_keywords for this faq_id
            try {
                $kStmt = $pdo->prepare("SELECT keyword FROM faq_keywords WHERE faq_id = :fid");
                $kStmt->execute([':fid' => $row['faq_id']]);
                $kws = $kStmt->fetchAll();
                foreach ($kws as $kw) {
                    $kScore = llogaritNgjashmerine($searchMsg, $kw['keyword'] ?? '');
                    if ($kScore > $bestScore) {
                        $bestScore = $kScore;
                        $bestFAQ = [
                            'question' => $row['question'],
                            'normalized_question' => normalize($row['question']),
                            'answer'   => $row['answer'],
                        ];
                    }
                }
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {}
 
    if ($bestFAQ && $bestScore >= 60) {
        $answer  = htmlspecialchars($bestFAQ['answer'] ?? 'Nuk ka përgjigje të regjistruar.');
        $question = htmlspecialchars($bestFAQ['question'] ?? '');
        $reply   = "<strong>❓ {$question}</strong><br><br>{$answer}<br><br>Mund të më pyesni ndonjë gjë tjetër?";
        return [
            "status"       => "success",
            "matched_type" => "faq_match",
            "location_id"  => null,
            "suggested_match" => $question,
            "reply"        => $reply,
            "score"        => $bestScore,
        ];
    }
 
    return null;
}
 
// =======================
// LOCATION SEARCH
// =======================
 
function searchLocation(PDO $pdo, string $message): ?array
{
    $searchMessage = normalizeForSearch($message);
    if ($searchMessage === '') return null;
 
    // STRICT: Number matching first
    preg_match_all('/\d+/', $message, $matches);
    if (!empty($matches[0])) {
        foreach ($matches[0] as $nr) {
            $stmt = $pdo->prepare("SELECT * FROM locations WHERE name = :nr AND is_active = true LIMIT 1");
            $stmt->execute([':nr' => $nr]);
            $exactRoom = $stmt->fetch();
            if ($exactRoom) {
                return [
                    "status" => "success", "matched_type" => "exact_name",
                    "location_id" => $exactRoom['location_id'],
                    "suggested_match" => $exactRoom['name'],
                    "reply" => krijoPergjigjeLokacioni($exactRoom, "")
                ];
            }
 
            $stmt = $pdo->prepare("SELECT * FROM locations WHERE name LIKE :nr_like AND is_active = true LIMIT 1");
            $stmt->execute([':nr_like' => "%$nr%"]);
            $likeMatch = $stmt->fetch();
            if ($likeMatch) {
                return [
                    "status" => "success", "matched_type" => "location_like_match",
                    "location_id" => $likeMatch['location_id'],
                    "suggested_match" => $likeMatch['name'],
                    "reply" => krijoPergjigjeLokacioni($likeMatch, "")
                ];
            }
        }
    }
 
    // Keyword fuzzy search
    $stmt = $pdo->query("
        SELECT k.*, l.location_id AS loc_id, l.name, l.description, l.floor, l.is_active
        FROM keywords k
        INNER JOIN locations l ON k.location_id = l.location_id
        WHERE l.is_active = true
    ");
    $keywords = $stmt->fetchAll();
 
    $bestKeywordMatch = null;
    $bestKeywordScore = 0;
    foreach ($keywords as $row) {
        $score = llogaritNgjashmerine($searchMessage, $row['keyword'] ?? '');
        if ($score > $bestKeywordScore) {
            $bestKeywordScore = $score;
            $bestKeywordMatch = $row;
        }
    }
    if ($bestKeywordMatch && $bestKeywordScore >= 80) {
        return [
            "status" => "success", "matched_type" => "keyword_fuzzy",
            "location_id" => $bestKeywordMatch['loc_id'],
            "suggested_match" => $bestKeywordMatch['name'],
            "reply" => krijoPergjigjeLokacioni($bestKeywordMatch, "")
        ];
    }
 
    // Location name/desc fuzzy search
    $stmt = $pdo->query("SELECT * FROM locations WHERE is_active = true");
    $locations = $stmt->fetchAll();
 
    $bestLocationMatch = null;
    $bestLocationScore = 0;
    foreach ($locations as $row) {
        $scoreName = llogaritNgjashmerine($searchMessage, $row['name'] ?? '');
        $scoreDesc = llogaritNgjashmerine($searchMessage, $row['description'] ?? '');
        $score = max($scoreName, (int)($scoreDesc / 2));
        if ($score > $bestLocationScore) {
            $bestLocationScore = $score;
            $bestLocationMatch = $row;
        }
    }
    if ($bestLocationMatch && $bestLocationScore >= 80) {
        return [
            "status" => "success", "matched_type" => "location_fuzzy",
            "location_id" => $bestLocationMatch['location_id'],
            "suggested_match" => $bestLocationMatch['name'],
            "reply" => krijoPergjigjeLokacioni($bestLocationMatch, "")
        ];
    }
    if ($bestLocationMatch && $bestLocationScore >= 50) {
        return [
            "status" => "suggestion", "matched_type" => "suggestion",
            "location_id" => $bestLocationMatch['location_id'],
            "suggested_match" => $bestLocationMatch['name'],
            "reply" => krijoPergjigjeSugjeruese($bestLocationMatch)
        ];
    }
 
    return null;
}
 
// =======================
// SMART CHATBOT — MAIN
// =======================
 
function merrPergjigjen(PDO $pdo, string $message): array
{
    $searchMessage = normalizeForSearch($message);
 
    if ($searchMessage === '') {
        return [
            "status" => "empty", "matched_type" => "empty",
            "location_id" => null, "suggested_match" => null,
            "reply" => "Ju lutem shkruani një pyetje për Kolegjin AAB."
        ];
    }
 
    $intent = detectIntent($message);
 
    // --- Route by intent ---
 
    if ($intent === 'faq') {
        $faqResult = searchFAQ($pdo, $message);
        if ($faqResult) return $faqResult;
 
        // Fallback: maybe it's also a location
        $locResult = searchLocation($pdo, $message);
        if ($locResult) return $locResult;
    }
 
    if ($intent === 'location') {
        $locResult = searchLocation($pdo, $message);
        if ($locResult) return $locResult;
 
        // Fallback: maybe it's an FAQ
        $faqResult = searchFAQ($pdo, $message);
        if ($faqResult) return $faqResult;
    }
 
    // --- intent === 'unknown': try both ---
    if ($intent === 'unknown') {
        $locResult = searchLocation($pdo, $message);
        $faqResult = searchFAQ($pdo, $message);
 
        // Return whichever matched with success
        if ($locResult && $locResult['status'] === 'success') return $locResult;
        if ($faqResult && $faqResult['status'] === 'success') return $faqResult;
 
        // Return suggestion if any
        if ($locResult && $locResult['status'] === 'suggestion') return $locResult;
    }
 
    // --- Nothing found ---
    return [
        "status" => "not_found",
        "matched_type" => "unresolved",
        "location_id" => null,
        "suggested_match" => null,
        "reply" => "Më vjen keq, nuk arrita ta kuptoj saktë pyetjen tuaj.<br><br>" .
                   "Provoni të shkruani:<br>" .
                   "📍 <strong>Ku është salla 108?</strong> — për lokacione<br>" .
                   "❓ <strong>Si mund të regjistrohem?</strong> — për informacione të tjera"
    ];
}
 
// =======================
// INPUT HANDLING
// =======================
 
$user_raw_message = $_POST['message'] ?? "";
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($user_raw_message))) {
 
    $response = merrPergjigjen($pdo, $user_raw_message);
    $clean_msg = normalize($user_raw_message);
    $currentTime = date('H:i');
 
    $_SESSION['chat_history'][] = [
        'role' => 'user',
        'content' => htmlspecialchars($user_raw_message),
        'time' => $currentTime
    ];
 
    $_SESSION['chat_history'][] = [
        'role' => 'bot',
        'content' => $response['reply'],
        'time' => $currentTime
    ];
 
    // Save to chat_messages
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages 
            (user_question, bot_response, matched_type, matched_location_id)
            VALUES (:user_question, :bot_response, :matched_type, :matched_location_id)
        ");
        $stmt->execute([
            ':user_question' => $clean_msg,
            ':bot_response' => strip_tags($response['reply']),
            ':matched_type' => $response['matched_type'],
            ':matched_location_id' => $response['location_id']
        ]);
    } catch (Exception $e) {}
 
    // Save unresolved questions
    if ($response['status'] === 'not_found' || $response['status'] === 'suggestion') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO unresolved_questions
                (user_question, normalized_question, suggested_match, status)
                VALUES (:user_question, :normalized_question, :suggested_match, :status)
            ");
            $stmt->execute([
                ':user_question' => $user_raw_message,
                ':normalized_question' => $clean_msg,
                ':suggested_match' => $response['suggested_match'],
                ':status' => $response['status'] === 'suggestion' ? 'suggested' : 'pending'
            ]);
        } catch (Exception $e) {}
    }
 
    header("Location: index.php");
    exit();
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
            background: #b60000;
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
                    <p>Pyet për salla, zyra, administratë, bibliotekë ose informata rreth Kolegjit AAB.</p>
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