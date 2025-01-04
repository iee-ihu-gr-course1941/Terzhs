<?php
// game.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $token = $_POST['token'] ?? '';

    if (!$action) {
        echo json_encode(['status' => 'error', 'message' => 'Action is required']);
        exit;
    }

    try {
        if ($action === 'create') {
            // Create a new game
            if (empty($token)) {
                echo json_encode(['status' => 'error', 'message' => 'Player token is required']);
                exit;
            }

            $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
            $stmt->execute([':token' => $token]);
            $player = $stmt->fetch();

            if ($player) {
                $player1_id = $player['id'];

                // Insert the new game into the database with player1_id and status 'waiting'
                $stmt = $db->prepare("
                    INSERT INTO games (player1_id, current_turn_player, status) 
                    VALUES (:player1_id, :current_turn_player, 'waiting')
                ");
                $stmt->execute([
                    ':player1_id'         => $player1_id,
                    ':current_turn_player'=> $player1_id
                ]);

                // Cast the lastInsertId() to int so the JSON returns a numeric value
                $game_id = (int)$db->lastInsertId();

                echo json_encode([
                    'status'      => 'success',
                    'game_id'     => $game_id,
                    'game_status' => 'waiting'
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
            }

        } elseif ($action === 'join') {
            // Join an existing game in "waiting" status
            if (empty($token)) {
                echo json_encode(['status' => 'error', 'message' => 'Player token is required']);
                exit;
            }

            $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
            $stmt->execute([':token' => $token]);
            $player = $stmt->fetch();

            if ($player) {
                $player2_id = $player['id'];

                // Find a game in 'waiting' status that has not yet been joined by player2
                $stmt = $db->prepare("SELECT id FROM games WHERE status = 'waiting' LIMIT 1");
                $stmt->execute();
                $game = $stmt->fetch();

                if ($game) {
                    // Update the game to assign player2 and change status to 'in_progress'
                    $stmt = $db->prepare("
                        UPDATE games 
                        SET player2_id = :player2_id, status = 'in_progress'
                        WHERE id = :game_id
                    ");
                    $stmt->execute([
                        ':player2_id' => $player2_id,
                        ':game_id'    => $game['id']
                    ]);

                    // Cast the fetched game_id to int for consistency in JSON
                    $game_id_numeric = (int)$game['id'];

                    echo json_encode([
                        'status'      => 'success',
                        'game_id'     => $game_id_numeric,
                        'game_status' => 'in_progress'
                    ]);
                } else {
                    echo json_encode([
                        'status'  => 'error',
                        'message' => 'No available game to join'
                    ]);
                }
            } else {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Invalid player token'
                ]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to process request: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
