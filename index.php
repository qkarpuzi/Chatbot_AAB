<?php
include "chatbot.php";

$response = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = $_POST["message"];
    $response = getBotResponse($message);
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

<?php if ($response): ?>
    <p><b>Bot:</b> <?php echo $response; ?></p>
<?php endif; ?>

</body>
</html>