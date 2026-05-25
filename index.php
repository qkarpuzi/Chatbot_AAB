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
        'waiting_for_clarification' => null
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
        'kallxom', 'qka', 'cfare', 'salla', 'salle', 'zyra', 'zyre'
    ];
 
    $words = explode(' ', $text);
    $filteredWords = [];
    foreach ($words as $word) {
        $word = trim($word);
        // Mos e fshij numrin "2" ose "1" si stopword nëse përdoret si specifikim lokacioni
        if ($word !== '' && (!in_array($word, $stopWords) || is_numeric($word))) {
            $filteredWords[] = $word;
        }
    }
    return trim(implode(' ', $filteredWords));
}

function hasStandaloneToken(string $haystack, string $token): bool
{
    $token = trim(mb_strtolower($token, 'UTF-8'));
    if ($token === '') {
        return false;
    }

    return (bool)preg_match(
        '/(?:^|[\s\-\/])' . preg_quote($token, '/') . '(?:$|[\s\-\/])/iu',
        mb_strtolower($haystack, 'UTF-8')
    );
}

function extractVariantToken(string $text): ?string
{
    $normalized = normalize($text);
    if ($normalized === '') {
        return null;
    }

    preg_match_all('/\b([0-9]+|i|ii|iii|iv|v|vi|vii|viii|ix|x)\b/iu', $normalized, $m);
    if (empty($m[1])) {
        return null;
    }

    $token = end($m[1]);
    return mb_strtoupper((string)$token, 'UTF-8');
}

function normalizeCompactCode(string $text): string
{
    $text = normalize($text);
    return (string)preg_replace('/[^a-z0-9]/iu', '', $text);
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
 
    // HAPI I RI: Mapimi i vlerave sinonime për numrat dhe numrat romakë
    // Kjo bën që "2" dhe "ii" të trajtohen si e njëjta gjë, kurse "1" dhe "i" si e njëjta gjë.
    $map1 = false; $map2 = false;
    
    if (in_array('2', $words1) || in_array('ii', $words1)) $map1 = 'grupi_2';
    if (in_array('1', $words1) || in_array('i', $words1)) $map1 = 'grupi_1';
    
    if (in_array('2', $words2) || in_array('ii', $words2)) $map2 = 'grupi_2';
    if (in_array('1', $words2) || in_array('i', $words2)) $map2 = 'grupi_1';
 
    // Nëse njëra pyetje specifikon indeksin (1 ose 2) dhe lokacioni tjetër ka indeks tjetër, penalizohet me 0
    if (($map1 || $map2) && ($map1 !== $map2)) {
        return 0;
    }
 
    $score = 0;
    similar_text($text1, $text2, $percent);
    $score += (int)$percent;
 
    foreach ($words1 as $w1) {
        foreach ($words2 as $w2) {
            if ($w1 === $w2) {
                $score += 50; // Rritet pesha e fjalëve të sakta si "help" ose "desk"
            }
        }
    }
    return $score;
}
 
function krijoTekstinEKatit($floorRaw): string
{
    if ($floorRaw === null || $floorRaw === '') return "në një kat që nuk është specifikuar ende";
    $floor = trim((string)$floorRaw);
    if ($floor === '0') return "në katin përdhesë (Kati 0)";
    if ($floor === '-1') return "në bodrum (Kati -1)";
    return "në katin " . htmlspecialchars($floor);
}
 
function krijoPergjigjeLokacioni(array $location): string
{
    $name = htmlspecialchars(trim($location['name'] ?? 'lokacioni i kërkuar'));
    $description = htmlspecialchars(trim($location['description'] ?? ''));
    $floorText = krijoTekstinEKatit($location['floor'] ?? null);
 
    $reply = "<strong>{$name}</strong> ndodhet {$floorText} të Kolegjit AAB.";
    if ($description !== '') {
        $reply .= " " . $description;
    }
    $reply .= "<br><br>Mund të më pyesni ndonjë gjë tjetër?";
    return $reply;
}

function krijoPergjigjeKontekstualeLokacioni(array $location): string
{
    $name = htmlspecialchars(trim($location['name'] ?? 'Ai lokacion'));
    $floorText = krijoTekstinEKatit($location['floor'] ?? null);
    return "Siç e përmendëm, <strong>{$name}</strong> gjendet ekzaktësisht <strong>{$floorText}</strong>.<br><br>A keni nevojë për ndonjë udhëzim tjetër?";
}

function krijoPergjigjeSugjeruese(array $location): string
{
    $name = htmlspecialchars(trim($location['name'] ?? 'lokacion i panjohur'));
    $floorText = krijoTekstinEKatit($location['floor'] ?? null);

    return "Nuk jam plotësisht i sigurt, por ndoshta keni menduar për <strong>{$name}</strong> që ndodhet {$floorText}.";
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
 
    if ($bestFAQ && $bestScore >= 60) {
        $answer  = htmlspecialchars($bestFAQ['answer'] ?? '');
        $question = htmlspecialchars($bestFAQ['question'] ?? '');
        return [
            "status" => "success", "matched_type" => "faq_match", "location_id" => null, "faq_id" => $bestFAQ['faq_id'] ?? null,
            "suggested_match" => $question, "reply" => "<strong>❓ {$question}</strong><br><br>{$answer}<br><br>Mund të më pyesni ndonjë gjë tjetër?"
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
    $cleanMessage = normalize($message);

    if ($searchMessage === '' && $cleanMessage === '') {
        return null;
    }

    $queryText = $searchMessage !== '' ? $searchMessage : $cleanMessage;
    $variantToken = extractVariantToken($cleanMessage);

    // Keep structural prefixes (e.g., A-LAB/B-LAB) that can be lost by stop-word filtering.
    if (preg_match('/\b[a-z]\s*lab\b/iu', $cleanMessage) || mb_strpos($cleanMessage, '-') !== false) {
        $queryText = $cleanMessage;
    }

    if (
        strpos($cleanMessage, 'shkenca') === false && strpos($cleanMessage, 'komp') === false &&
        strpos($cleanMessage, 'jurid') === false && strpos($cleanMessage, 'ekonom') === false &&
        strpos($cleanMessage, 'gjuhe') === false && strpos($cleanMessage, 'mjekes') === false &&
        strpos($cleanMessage, 'qendror') === false
    ) {
        if (mb_strpos($cleanMessage, 'dekanat') !== false) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'dekanat';
            return [
                'status' => 'clarification', 'matched_type' => 'disambiguation_prompt',
                'location_id' => null, 'faq_id' => null, 'suggested_match' => null,
                'reply' => 'Cilin dekanat po kërkoni? (p.sh., Shkenca Kompjuterike, Juridik, Ekonomik, etj.)'
            ];
        }

        if (mb_strpos($cleanMessage, 'administrat') !== false) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'administrata';
            return [
                'status' => 'clarification', 'matched_type' => 'disambiguation_prompt',
                'location_id' => null, 'faq_id' => null, 'suggested_match' => null,
                'reply' => 'Për cilën administratë bëhet fjalë? (p.sh., Administratën Qendrore apo të ndonjë Fakulteti specifik?)'
            ];
        }

        if (mb_strpos($cleanMessage, 'referent') !== false) {
            $_SESSION['last_context']['waiting_for_clarification'] = 'referent';
            return [
                'status' => 'clarification', 'matched_type' => 'disambiguation_prompt',
                'location_id' => null, 'faq_id' => null, 'suggested_match' => null,
                'reply' => 'Për cilët referentë po pyesni? (Ju lutem specifikoni fakultetin, p.sh., Shkenca Kompjuterike, Fakulteti Ekonomik, etj.)'
            ];
        }
    }

    $stmt = $pdo->prepare(" 
        SELECT
            l.location_id,
            l.name,
            l.description,
            l.floor,
            l.room_number,
            l.is_active,
            COALESCE(string_agg(DISTINCT k.keyword, ' '), '') AS keyword_blob,
            COALESCE(string_agg(DISTINCT COALESCE(k.normalized_keyword, ''), ' '), '') AS normalized_keyword_blob
        FROM locations l
        LEFT JOIN keywords k ON k.location_id = l.location_id
        WHERE l.is_active = true
          AND (
              lower(l.name) LIKE :like_query
              OR lower(COALESCE(l.description, '')) LIKE :like_query
              OR lower(COALESCE(l.room_number, '')) = :query_exact
              OR lower(COALESCE(l.room_number, '')) LIKE :query_prefix
              OR EXISTS (
                  SELECT 1
                  FROM keywords k2
                  WHERE k2.location_id = l.location_id
                    AND (
                        lower(k2.keyword) LIKE :like_query
                        OR lower(COALESCE(k2.normalized_keyword, '')) LIKE :like_query
                    )
              )
          )
        GROUP BY l.location_id, l.name, l.description, l.floor, l.room_number, l.is_active
        LIMIT 120
    ");

    $queryLower = mb_strtolower($queryText, 'UTF-8');
    $stmt->execute([
        ':like_query' => '%' . str_replace(' ', '%', $queryLower) . '%',
        ':query_exact' => $queryLower,
        ':query_prefix' => $queryLower . '%',
    ]);
    $candidates = $stmt->fetchAll();

    if (empty($candidates)) {
        return null;
    }

    $queryCompact = normalizeCompactCode($queryText);
    $splitMatches = [];
    $isCompactCodeQuery = (bool)preg_match('/^[a-z]{1,3}\d{1,3}$/iu', $queryCompact);
    $canUseTextSplit = (mb_strlen($queryText, 'UTF-8') >= 3 && $variantToken === null);

    foreach ($candidates as $candidate) {
        $candidateName = (string)($candidate['name'] ?? '');
        $candidateCompact = normalizeCompactCode($candidateName);
        $candidateNorm = normalize($candidateName);

        if ($isCompactCodeQuery && $candidateCompact !== '' && strpos($candidateCompact, $queryCompact) === 0 && $candidateCompact !== $queryCompact) {
            $splitMatches[] = $candidate;
            continue;
        }

        if ($canUseTextSplit) {
            if (preg_match('/^' . preg_quote($queryText, '/') . '[\s\-–\/]+([0-9]+|i|ii|iii|iv|v|vi|vii|viii|ix|x)\b/iu', $candidateNorm)) {
                $splitMatches[] = $candidate;
            }
        }
    }

    if (count($splitMatches) === 1) {
        $only = $splitMatches[0];
        return [
            'status' => 'success',
            'matched_type' => 'location_split_single',
            'location_id' => $only['location_id'],
            'faq_id' => null,
            'suggested_match' => $only['name'],
            'reply' => krijoPergjigjeLokacioni($only)
        ];
    }

    if (count($splitMatches) > 1) {
        usort($splitMatches, function (array $a, array $b) {
            return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $_SESSION['last_context']['waiting_for_clarification'] = $queryText;

        $top = array_slice($splitMatches, 0, 8);
        $options = [];
        foreach ($top as $row) {
            $options[] = '<strong>' . htmlspecialchars((string)($row['name'] ?? '')) . '</strong>';
        }

        $remaining = count($splitMatches) - count($top);
        $title = $isCompactCodeQuery ? mb_strtoupper($queryCompact, 'UTF-8') : $queryText;
        $reply = 'Për <strong>' . htmlspecialchars($title) . '</strong> gjeta disa lokacione: ' . implode(', ', $options) . '.';

        if ($remaining > 0) {
            $reply .= ' dhe ' . $remaining . ' të tjera.';
        }

        if ($isCompactCodeQuery) {
            $reply .= '<br><br>Ju lutem shkruani kodin e plotë, p.sh: <strong>' . htmlspecialchars(mb_strtoupper($queryCompact, 'UTF-8')) . '-103</strong>.';
        } else {
            $reply .= '<br><br>Ju lutem specifikoni variantin e plotë (p.sh. me numrin ose romakun në fund).';
        }

        return [
            'status' => 'clarification',
            'matched_type' => 'location_split_disambiguation',
            'location_id' => null,
            'faq_id' => null,
            'suggested_match' => null,
            'reply' => $reply
        ];
    }

    $queryHasHelpDesk = mb_strpos($cleanMessage, 'help desk') !== false || mb_strpos($cleanMessage, 'it help desk') !== false;

    foreach ($candidates as &$row) {
        $name = (string)($row['name'] ?? '');
        $description = (string)($row['description'] ?? '');
        $keywordBlob = (string)($row['keyword_blob'] ?? '');
        $normalizedKeywordBlob = (string)($row['normalized_keyword_blob'] ?? '');
        $roomNumber = trim((string)($row['room_number'] ?? ''));

        $scoreName = llogaritNgjashmerine($queryText, $name);
        $scoreDesc = llogaritNgjashmerine($queryText, $description);
        $scoreKeyword = max(
            llogaritNgjashmerine($queryText, $keywordBlob),
            llogaritNgjashmerine($queryText, $normalizedKeywordBlob)
        );

        $score = max($scoreName, $scoreKeyword, (int)($scoreDesc / 2));

        $nameNorm = normalize($name);
        $allNorm = normalize($name . ' ' . $keywordBlob . ' ' . $normalizedKeywordBlob);

        if ($nameNorm === $queryText) {
            $score += 140;
        }

        if (hasStandaloneToken($allNorm, $queryText)) {
            $score += 70;
        }

        if ($queryHasHelpDesk && mb_strpos($allNorm, 'help desk') !== false) {
            $score += 45;
        }

        if ($variantToken !== null) {
            $variantLower = mb_strtolower($variantToken, 'UTF-8');

            if (hasStandaloneToken($allNorm, $variantLower) || ($roomNumber !== '' && mb_strtoupper($roomNumber, 'UTF-8') === $variantToken)) {
                $score += 170;
            } else {
                if (preg_match('/\b([0-9]+|i|ii|iii|iv|v|vi|vii|viii|ix|x)\b/iu', $allNorm)) {
                    $score -= 120;
                }
            }
        }

        $row['_score'] = $score;
    }
    unset($row);

    usort($candidates, function (array $a, array $b) {
        return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
    });

    $best = $candidates[0] ?? null;
    if (!$best) {
        return null;
    }

    if ($queryHasHelpDesk && $variantToken === null && count($candidates) > 1) {
        $second = $candidates[1];
        $firstName = normalize((string)($best['name'] ?? ''));
        $secondName = normalize((string)($second['name'] ?? ''));

        if (mb_strpos($firstName, 'help desk') !== false && mb_strpos($secondName, 'help desk') !== false) {
            if (abs((int)($best['_score'] ?? 0) - (int)($second['_score'] ?? 0)) <= 18) {
                return [
                    'status' => 'clarification',
                    'matched_type' => 'location_disambiguation',
                    'location_id' => null,
                    'faq_id' => null,
                    'suggested_match' => null,
                    'reply' => 'Po i gjej të dyja: <strong>' . htmlspecialchars((string)$best['name']) . '</strong> dhe <strong>' . htmlspecialchars((string)$second['name']) . '</strong>.<br><br>Ju lutem specifikoni: <strong>IT Help Desk I</strong> apo <strong>IT Help Desk II</strong>?'
                ];
            }
        }
    }

    $bestScore = (int)($best['_score'] ?? 0);

    if ($bestScore >= 105) {
        return [
            'status' => 'success', 'matched_type' => 'location_db_ranked',
            'location_id' => $best['location_id'], 'faq_id' => null,
            'suggested_match' => $best['name'], 'reply' => krijoPergjigjeLokacioni($best)
        ];
    }

    if ($bestScore >= 72) {
        return [
            'status' => 'suggestion', 'matched_type' => 'location_db_suggestion',
            'location_id' => $best['location_id'], 'faq_id' => null,
            'suggested_match' => $best['name'], 'reply' => krijoPergjigjeSugjeruese($best)
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
    
    if (!empty($_SESSION['last_context']['waiting_for_clarification'])) {
        $cType = $_SESSION['last_context']['waiting_for_clarification'];
        $combinedQuery = $cType . " " . $message;
        $_SESSION['last_context']['waiting_for_clarification'] = null;
        
        $result = searchLocation($pdo, $combinedQuery);
        if ($result && $result['status'] === 'success') return $result;
    }

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
            "reply" => "Ju lutem shkruani një pyetje më specifike për Kolegjin AAB."
        ];
    }
 
    $locResult = searchLocation($pdo, $message);
    if ($locResult) return $locResult;
 
    $faqResult = searchFAQ($pdo, $message);
    if ($faqResult) return $faqResult;
 
    return [
        "status" => "not_found", "matched_type" => "unresolved", "location_id" => null, "faq_id" => null, "suggested_match" => null,
        "reply" => "Më vjen keq, nuk arrita ta gjej këtë lokacion. Provoni të shkruani më thjeshtë, p.sh: <strong>IT-DESK II</strong> ose <strong>Salla 108</strong>."
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