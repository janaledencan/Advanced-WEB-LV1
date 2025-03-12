<?php
$servername = "localhost";
$username = "root"; // Default user u XAMPP-u
$password = ""; // Default password u XAMPP-u
$database = "radovi";

// Stvaranje konekcije
$conn = new mysqli($servername, $username, $password, $database);

// Provjera konekcije
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
