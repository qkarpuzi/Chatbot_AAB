<?php 
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "aab_chatbot_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo "<script>console.log('Connection failed: " . $conn->connect_error . "');</script>";
}else{
    echo "<script>console.log('Connected successfully');</script>";
}
?>