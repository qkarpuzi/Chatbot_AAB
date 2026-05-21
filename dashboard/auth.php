<?php
// Start session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    
    // Bounce them up one directory level back to the root login page
    header("Location: ../login.php");
    
    // CRITICAL: This terminates execution so the server refuses to send the dashboard HTML
    exit(); 
}
?>