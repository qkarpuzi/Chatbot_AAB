<?php
session_start();
 
// Inicializimi i historisë së chat-it dhe kontekstit
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}
if (!isset($_SESSION['last_context'])) {
    $_SESSION['last_context'] = [
        'type' => null,
        'id' => null,
        'name_or_query' => null,
        'waiting_for_clarification' => null // Ruhen gjendjet: 'dekanat', 'referent', 'salla', 'zyra', 'laborator', 'tualet'
    ];
}
 
// Logjika për butonin "Pastro"
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    $_SESSION['chat_history'] = [];
    $_SESSION['last_context'] = ['type' => null, 'id' => null, 'name_or_query' => null, 'waiting_for_clarification' => null];
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
        'kallxom', 'qka', 'cfare'
    ];
 
    $words = explode(' ', $text);
    $filteredWords = [];
    foreach ($words as $word) {
        $word = trim($word);
        if ($word !== '' && (!in_array($word, $stopWords) || is_numeric($word))) {
            $filteredWords[] = $word;
        }
    }
    return trim(implode(' ', $filteredWords));
}
 
// =======================
// FUZZY SIMILARITY + EXACT MATCH CHECK
// =======================
function llogaritNgjashmerine(string $text1, string $text2): int
{
    $text1 = normalizeForSearch($text1);
    $text2 = normalizeForSearch($text2);
 
    if ($text1 === '' || $text2 === '') return 0;
    if ($text1 === $text2) return 100;
 
    $words1 = explode(' ', $text1);
    $words2 = explode(' ', $text2);
 
    // Mapimi dhe kontrolli i rreptë i numrave dhe indekseve romake
    $index1 = null; $index2 = null;
    
    if (in_array('1', $words1) || in_array('i', $words1))   $index1 = 1;
    if (in_array('2', $words1) || in_array('ii', $words1))  $index1 = 2;
    if (in_array('3', $words1) || in_array('iii', $words1)) $index1 = 3;
    if (in_array('4', $words1) || in_array('iv', $words1))  $index1 = 4;
    
    if (in_array('1', $words2) || in_array('i', $words2))   $index2 = 1;
    if (in_array('2', $words2) || in_array('ii', $words2))  $index2 = 2;
    if (in_array('3', $words2) || in_array('iii', $words2)) $index2 = 3;
    if (in_array('4', $words2) || in_array('iv', $words2))  $index2 = 4;
 
    // Nëse specifikohet një indeks numerik i ndryshëm, refuzohet plotësisht kërkimi
    if (($index1 !== null || $index2 !== null) && ($index1 !== $index2)) {
        return 0;
    }
 
    $score = 0;
    similar_text($text1, $text2, $percent);
    $score += (int)$percent;
 
    // Pesha ekstra për fjalët kyçe identike
    foreach ($words1 as $w1) {
        foreach ($words2 as $w2) {
            if ($w1 === $w2) {
                $score += 60; 
            }
        }
    }
    return $score;
}
 
function krijoTekstinEKatit($floorRaw): string
{
    if ($floorRaw === null || $floorRaw === '') return "në një kat të pa-specifikuar";
    $floor = trim((string)$floorRaw);
    if ($floor === '0') return "në katin përdhesë (Kati 0)";
    if ($floor === '-1') return "në bodrum (Kati -1)";
    return "në katin " . htmlspecialchars($floor);
}
 
function krijoPergjigjeLokacioni(array $location): string
{
    $name = htmlspecialchars(trim($location['name'] ?? 'lokacioni'));
    $description = htmlspecialchars(trim($location['description'] ?? ''));
    $floorText = krijoTekstinEKatit($location['floor'] ?? null);
 
    $reply = "<strong>{$name}</strong> ndodhet {$floorText} të Kolegjit AAB.";
    if ($description !== '') $reply .= " " . $description;
    $reply .= "<br><br>Marrë parasysh këtë, a mund t'ju ndihmoj edhe me diçka tjetër?";
    return $reply;
}

function krijoPergjigjeKontekstualeLokacioni(array $location): string
{
    $name = htmlspecialchars(trim($location['name'] ?? 'Ai lokacion'));
    $floorText = krijoTekstinEKatit($location['floor'] ?? null);
    return "Siç e përmendëm, <strong>{$name}</strong> gjendet ekzaktësisht <strong>{$floorText}</strong>.";
}
 
// =======================
// FAQ SEARCH
// =======================
function searchFAQ(PDO $pdo, string $message): ?array
{
    $searchMsg = normalizeForSearch($message);
    if ($searchMsg === '') return null;
    $bestScore = 0; $bestFAQ = null;
 
    try {
        $stmt = $pdo->query("SELECT * FROM faq_questions WHERE is_active = true");
        $faqQuestions = $stmt->fetchAll();
        foreach ($faqQuestions as $row) {
            $scoreQ  = llogaritNgjashmerine($searchMsg, $row['question'] ?? '');
            $scoreNQ = llogaritNgjashmerine($searchMsg, $row['normalized_question'] ?? '');
            $score   = max($scoreQ, $scoreNQ);
            if ($score > $bestScore) { $bestScore = $score; $bestFAQ = $row; }
        }
    } catch (Exception $e) {}
 
    if ($bestFAQ && $bestScore >= 75) {
        $answer  = htmlspecialchars($bestFAQ['answer'] ?? '');
        $question = htmlspecialchars($bestFAQ['question'] ?? '');
        return [
            "status" => "success", "matched_type" => "faq_match", "location_id" => null, "faq_id" => $bestFAQ['faq_id'] ?? null,
            "suggested_match" => $question, "reply" => "<strong>❓ {$question}</strong><br><br>{$answer}"
        ];
    }
    return null;
}
 
// =======================
// LOCATION SEARCH
// =======================
function searchLocation(PDO $pdo, string $message): ?array
{
    $cleanMessage = normalize($message);
    $searchMessage = normalizeForSearch($cleanMessage);
    if ($searchMessage === '') return null;

    // ----------------------------------------------------
    // STRATEGJIA: INTEGRAL CLARIFICATION CHECK (DISAMBIGUATION)
    // Kontrollojmë nëse ka ndonjë fjalë specifike përveç rrënjëve të përgjithshme
    // ----------------------------------------------------
    $kaSpecifikim = preg_match('/\d+/', $cleanMessage) || 
                    strpos($cleanMessage, 'shkenca') !== false || strpos($cleanMessage, 'komp') !== false || 
                    strpos($cleanMessage, 'jurid') !== false || strpos($cleanMessage, 'ekonom') !== false ||
                    strpos($cleanMessage, 'gjuhe') !== false || strpos($cleanMessage, 'mjekes') !== false ||
                    strpos($cleanMessage, 'qendror') !== false || strpos($cleanMessage, 'help') !== false || 
                    strpos($cleanMessage, 'desk') !== false || strpos($cleanMessage, 'it') !== false ||
                    strpos($cleanMessage, 'edu') !== false || strpos($cleanMessage, 'anatom') !== false ||
                    strpos($cleanMessage, 'kryesore') !== false || strpos($cleanMessage, 'diplom') !== false ||
                    strpos($cleanMessage, 'karrier') !== false || strpos($cleanMessage, 'financ') !== false ||
                    strpos($cleanMessage, 'protokol') !== false;

    if (!$kaSpecifikim) {
        // Kontrolli për laboratorë (edhe nëse përsëritet: "lab lab", "laboratori")
        if (preg_match('/^(lab|laborator|laboratori|labi)(\s+(lab|laborator|laboratori|labi))*$/', $searchMessage)) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'laborator';
            return [
                "status" => "clarification", "matched_type" => "disambiguation_prompt", "location_id" => null, "faq_id" => null, "suggested_match" => null,
                "reply" => "Cilin laborator po kërkoni? Ju lutem specifikoni emrin ose fakultetin (p.sh., Laboratori i Anatomisë, EDU-LAB, Laboratori 1 i Shkencave Kompjuterike, etj.)."
            ];
        }
        // Kontrolli për salla (edhe nëse përsëritet: "salla salla salla")
        if (preg_match('/^(salle|salla|sallat)(\s+(salle|salla|sallat))*$/', $searchMessage)) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'salla';
            return [
                "status" => "clarification", "matched_type" => "disambiguation_prompt", "location_id" => null, "faq_id" => null, "suggested_match" => null,
                "reply" => "Cilën sallë po kërkoni? Ju lutem specifikoni numrin e sallës (p.sh., Salla 108, Salla 202, etj.)."
            ];
        }
        // Kontrolli i ri inteligjent për zyra (p.sh. "zyra", "zyra zyra", "zyra zyra zyra")
        if (preg_match('/^(zyre|zyra|zyrat)(\s+(zyre|zyra|zyrat))*$/', $searchMessage)) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'zyra';
            return [
                "status" => "clarification", "matched_type" => "disambiguation_prompt", "location_id" => null, "faq_id" => null, "suggested_match" => null,
                "reply" => "Për cilën zyrë bëhet fjalë? Ju lutem specifikoni emrin ose funksionin e saj (p.sh., Zyra e Financave, Zyra e Karrierës, Zyra e Diplomave, etj.)."
            ];
        }
        if (mb_strpos($cleanMessage, 'dekanat') !== false) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'dekanat';
            return [
                "status" => "clarification", "matched_type" => "disambiguation_prompt", "location_id" => null, "faq_id" => null, "suggested_match" => null,
                "reply" => "Cilin dekanat po kërkoni? (p.sh., Shkenca Kompjuterike, Fakulteti Juridik, Fakulteti Ekonomik, etj.)"
            ];
        }
        if (mb_strpos($cleanMessage, 'referent') !== false) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'referent';
            return [
                "status" => "clarification", "matched_type" => "disambiguation_prompt", "location_id" => null, "faq_id" => null, "suggested_match" => null,
                "reply" => "Për cilët referentë po pyesni? Ju lutem specifikoni fakultetin (p.sh., Referentët e Shkencave Kompjuterike, Ekonomik, etj.)."
            ];
        }
        if (mb_strpos($cleanMessage, 'tualet') !== false || mb_strpos($cleanMessage, 'banjo') !== false) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'tualet';
            return [
                "status" => "clarification", "matched_type" => "disambiguation_prompt", "location_id" => null, "faq_id" => null, "suggested_match" => null,
                "reply" => "Në cilin kat po kërkoni tualet? Ju lutem specifikoni katin (p.sh., katin përdhesë, katin 1, katin 2, etj.)."
            ];
        }
    }

    // NËSE PYETET THJESHT "ADMINISTRATA" (pa asnjë specifikim), E FORCOJMË TË KËRKOJË KRYESOREN
    if (!$kaSpecifikim && (mb_strpos($cleanMessage, 'administrat') !== false || $searchMessage === 'admin')) {
        $searchMessage = 'administrata qendrore kryesore'; 
    }
 
    // Kontrolli ekzakt për numra dhomash të pastra
    preg_match_all('/\d+/', $message, $matches);
    if (!empty($matches[0]) && count($matches[0]) == 1 && mb_strlen($searchMessage) <= 4) {
        foreach ($matches[0] as $nr) {
            $stmt = $pdo->prepare("SELECT * FROM locations WHERE name = :nr AND is_active = true LIMIT 1");
            $stmt->execute([':nr' => $nr]);
            $exactRoom = $stmt->fetch();
            if ($exactRoom) {
                return [
                    "status" => "success", "matched_type" => "exact_name",
                    "location_id" => $exactRoom['location_id'], "faq_id" => null,
                    "suggested_match" => $exactRoom['name'], "reply" => krijoPergjigjeLokacioni($exactRoom)
                ];
            }
        }
    }
 
    // Kontrolli përmes Keywords tabelës
    $stmt = $pdo->query("
        SELECT k.*, l.location_id AS loc_id, l.name, l.description, l.floor, l.is_active
        FROM keywords k
        INNER JOIN locations l ON k.location_id = l.location_id
        WHERE l.is_active = true
    ");
    $keywords = $stmt->fetchAll();
 
    $bestKeywordMatch = null; $bestKeywordScore = 0;
    foreach ($keywords as $row) {
        $score = llogaritNgjashmerine($searchMessage, $row['keyword'] ?? '');
        if ($score > $bestKeywordScore) { $bestKeywordScore = $score; $bestKeywordMatch = $row; }
    }
    if ($bestKeywordMatch && $bestKeywordScore >= 75) {
        return [
            "status" => "success", "matched_type" => "keyword_fuzzy",
            "location_id" => $bestKeywordMatch['loc_id'], "faq_id" => null,
            "suggested_match" => $bestKeywordMatch['name'], "reply" => krijoPergjigjeLokacioni($bestKeywordMatch)
        ];
    }
 
    // Kontrolli direkt në tabelën Locations
    $stmt = $pdo->query("SELECT * FROM locations WHERE is_active = true");
    $locations = $stmt->fetchAll();
 
    $bestLocationMatch = null; $bestLocationScore = 0;
    foreach ($locations as $row) {
        $scoreName = llogaritNgjashmerine($searchMessage, $row['name'] ?? '');
        if ($scoreName > $bestLocationScore) { $bestLocationScore = $scoreName; $bestLocationMatch = $row; }
    }
    if ($bestLocationMatch && $bestLocationScore >= 75) {
        return [
            "status" => "success", "matched_type" => "location_fuzzy",
            "location_id" => $bestLocationMatch['location_id'], "faq_id" => null,
            "suggested_match" => $bestLocationMatch['name'], "reply" => krijoPergjigjeLokacioni($bestLocationMatch)
        ];
    }
 
    return null;
}
 
// =======================
// SMART CHATBOT — MAIN
// =======================
function merrPergjigjen(PDO $pdo, string $message): array
{
    $cleanMessage = normalize($message);
    $searchMessage = normalizeForSearch($message);
    
    // Trajtimi i pyetjes kontekstuale pasuese ("ku eshte ?")
    if ($searchMessage === '' && !empty($cleanMessage)) {
        if (!empty($_SESSION['last_context']['type']) && $_SESSION['last_context']['type'] === 'location') {
            $stmt = $pdo->prepare("SELECT * FROM locations WHERE location_id = :id AND is_active = true LIMIT 1");
            $stmt->execute([':id' => $_SESSION['last_context']['id']]);
            $loc = $stmt->fetch();
            if ($loc) {
                return [
                    "status" => "success", "matched_type" => "context_fallback",
                    "location_id" => $loc['location_id'], "faq_id" => null,
                    "suggested_match" => $loc['name'], "reply" => krijoPergjigjeKontekstualeLokacioni($loc)
                ];
            }
        }
    }
 
    if ($searchMessage === '') {
        return [
            "status" => "empty", "matched_type" => "empty", "location_id" => null, "faq_id" => null, "suggested_match" => null,
            "reply" => "Ju lutem shkruani një lokacion ose pyetje specifike për Kolegjin AAB."
        ];
    }
 
    $locResult = searchLocation($pdo, $message);
    if ($locResult) return $locResult;
 
    $faqResult = searchFAQ($pdo, $message);
    if ($faqResult) return $faqResult;
 
    return [
        "status" => "not_found", "matched_type" => "unresolved", "location_id" => null, "faq_id" => null, "suggested_match" => null,
        "reply" => "Më vjen keq, nuk arrita ta gjej këtë lokacion ose pyetje. Ju lutem specifikoni më saktë (p.sh. <strong>Salla 108</strong> ose <strong>IT-DESK I</strong>)."
    ];
}
 
// =======================
// INPUT HANDLING
// =======================
$user_raw_message = $_POST['message'] ?? "";
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($user_raw_message))) {
 
    // ----------------------------------------------------
    // ZGJIDHJA GLOBALE: BASHKIMI ME RADHË I KONTEKSTIT QË NË HYRJE TË INPUT-IT
    // ----------------------------------------------------
    if (!empty($_SESSION['last_context']['waiting_for_clarification'])) {
        $contextType = $_SESSION['last_context']['waiting_for_clarification'];
        $_SESSION['last_context']['waiting_for_clarification'] = null; // Fshihet menjëherë që të mos bllokohet sesioni
        
        // Përditësojmë variablin kryesor në nivel global për tërë procesin (Kërkim + Histori + Databazë)
        $user_raw_message = $contextType . " " . $user_raw_message;
    }

    $response = merrPergjigjen($pdo, $user_raw_message);
    $clean_msg = normalize($user_raw_message);
    $currentTime = date('H:i');
 
    if ($response['status'] === 'success' && $response['matched_type'] !== 'context_fallback') {
        if (!empty($response['location_id'])) {
            $_SESSION['last_context']['type'] = 'location';
            $_SESSION['last_context']['id'] = $response['location_id'];
            $_SESSION['last_context']['name_or_query'] = $response['suggested_match'];
        }
    }
 
    $_SESSION['chat_history'][] = ['role' => 'user', 'content' => htmlspecialchars($user_raw_message), 'time' => $currentTime];
    $_SESSION['chat_history'][] = ['role' => 'bot', 'content' => $response['reply'], 'time' => $currentTime];
 
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (user_question, bot_response, matched_type, matched_location_id, matched_faq_id)
            VALUES (:user_question, :bot_response, :matched_type, :matched_location_id, :matched_faq_id)
        ");
        $stmt->execute([
            ':user_question' => $clean_msg, ':bot_response' => strip_tags($response['reply']),
            ':matched_type' => $response['matched_type'], ':matched_location_id' => $response['location_id'],
            ':matched_faq_id' => $response['faq_id']
        ]);
    } catch (Exception $e) {}
 
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