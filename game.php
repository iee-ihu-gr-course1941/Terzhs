<?php
// game.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'create') {
        // Create a new game session
        $token = $_POST['token'];

        // Get player ID using the token
        $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
        $stmt->execute([':token' => $token]);
        $player = $stmt->fetch();

        if ($player) {
            $player1_id = $player['id'];

            // Create the game session
            $stmt = $db->prepare("INSERT INTO games (player1_id, current_turn_player, status) VALUES (:player1_id, :current_turn_player, 'waiting')");
            $stmt->execute([':player1_id' => $player1_id, ':current_turn_player' => $player1_id]);
            $game_id = $db->lastInsertId();

            // Get the current status of the newly created game
            $stmt = $db->prepare("SELECT status FROM games WHERE id = :game_id");
            $stmt->execute([':game_id' => $game_id]);
            $game = $stmt->fetch();

            echo json_encode(['game_id' => $game_id, 'status' => $game['status']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        }
        
    } elseif ($action == 'join') {
        // Join an existing game in waiting status
        $token = $_POST['token'];

        // Get player ID using the token
        $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
        $stmt->execute([':token' => $token]);
        $player = $stmt->fetch();

        if ($player) {
            $player2_id = $player['id'];

            // Find a waiting game
            $stmt = $db->prepare("SELECT id FROM games WHERE status = 'waiting' LIMIT 1");
            $stmt->execute();
            $game = $stmt->fetch();
            
            if ($game) {
                // Update game with player2 ID and change status
                $stmt = $db->prepare("UPDATE games SET player2_id = :player2_id, status = 'in_progress' WHERE id = :game_id");
                $stmt->execute([':player2_id' => $player2_id, ':game_id' => $game['id']]);

                // Get the current status of the game after joining
                $stmt = $db->prepare("SELECT status FROM games WHERE id = :game_id");
                $stmt->execute([':game_id' => $game['id']]);
                $gameStatus = $stmt->fetch();

                echo json_encode(['game_id' => $game['id'], 'status' => $gameStatus['status']]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No game available to join']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        }
    }
}
?>
