<?php
// player.php
require 'db_connect.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ensure the name is provided in the request
    if (!isset($_POST['name']) || empty($_POST['name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Player name is required']);
        exit;
    }

    $name = $_POST['name'];

    try {
        // Generate a random 16-character player token
        $player_token = bin2hex(random_bytes(16));
        
        // Insert the player details into the database
        $stmt = $db->prepare("
            INSERT INTO players (name, player_token, created_at) 
            VALUES (:name, :player_token, NOW())
        ");
        $stmt->execute([
            ':name' => $name,
            ':player_token' => $player_token
        ]);
        
        // Return a success message with the player token
        echo json_encode(['status' => 'success', 'player_token' => $player_token]);
    } catch (Exception $e) {
        // Return an error message if an exception occurs
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>