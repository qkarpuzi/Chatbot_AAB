<?php
include "chatbot.php";

$response = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = trim($_POST["message"]);

    if (!empty($message)) {
        $response = getBotResponse($message);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple PHP Chatbot</title>
</head>
<body>

<h2>Chatbot 🤖</h2>

<form method="POST">
    <input type="text" name="message" placeholder="Say something..." required>
    <button type="submit">Send</button>
</form>

<?php if (!empty($response)): ?>
    <p><b>Bot:</b> <?php echo htmlspecialchars($response); ?></p>
<?php endif; ?>

</body>
</html>