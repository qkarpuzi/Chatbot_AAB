<?php

header("Content-Type: application/json");

// =======================
// LIDHJA ME DATABAZE
// =======================

$conn = new mysqli("localhost", "root", "", "aab_chatbot_db");

if ($conn->connect_error) {

    die(json_encode([
        "status" => "error",
        "reply" => "Lidhja me databazen deshtoi."
    ]));
}

// =======================
// MERR INPUT NGA FRONTEND
// =======================

$data = json_decode(file_get_contents("php://input"), true);

// Kontrollo input bosh
if (!isset($data['message']) || empty(trim($data['message']))) {

    echo json_encode([
        "status" => "empty",
        "reply" => "Ju lutem shkruani nje pyetje."
    ]);

    exit;
}

// Mesazhi i userit
$message = strtolower(trim($data['message']));

// =======================
// FUNKSIONI PER GJETJE
// =======================

function merrPergjigjen($conn, $message)
{

    $sql = "SELECT * FROM locations WHERE is_active = 1";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        while ($row = $result->fetch_assoc()) {

            // Emri i lokacionit
            $locationName = strtolower($row['name']);

            // Kontrollon nese useri e permend lokacionin
            if (strpos($message, $locationName) !== false) {

                return [
                    "status" => "success",

                    "reply" =>
                        "📍 Lokacioni: " . $row['name'] .
                        "\n🏢 Kati: " . $row['floor'] .
                        "\n🚪 Dhoma: " . $row['room_number'] .
                        "\n📝 Pershkrimi: " . $row['description'],

                    "location_id" => $row['location_id']
                ];
            }
        }
    }

    // Nese nuk gjendet
    return [
        "status" => "not_found",
        "reply" => "Nuk u gjet asnje lokacion."
    ];
}

// =======================
// THIRR FUNKSIONIN
// =======================

$response = merrPergjigjen($conn, $message);

// =======================
// RUAJ MESAZHIN NE DATABASE
// =======================

$userQuestion = $conn->real_escape_string($message);
$botReply = $conn->real_escape_string($response['reply']);

$matchedLocationId = isset($response['location_id'])
    ? $response['location_id']
    : "NULL";

$insertSql = "
INSERT INTO chat_messages
(user_question, bot_response, matched_type, matched_location_id)

VALUES
(
    '$userQuestion',
    '$botReply',
    'location',
    $matchedLocationId
)
";

$conn->query($insertSql);

// =======================
// KTHE JSON RESPONSE
// =======================

echo json_encode($response);

?>