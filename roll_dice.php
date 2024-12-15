<?php
// roll_dice.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;

    // Check if game ID and token are provided
    if (!$game_id || !$token) {
        echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required']);
        exit;
    }

    try {
        // Verify player and game status
        $stmt = $db->prepare("
            SELECT p.id as player_id, g.current_turn_player, g.status, pc.has_rolled 
            FROM players p 
            JOIN games g ON g.id = :game_id
            JOIN player_columns pc ON pc.game_id = g.id AND pc.player_id = p.id
            WHERE p.player_token = :token
        ");
        $stmt->execute([':token' => $token, ':game_id' => $game_id]);
        $result = $stmt->fetch();

        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token']);
            exit;
        }

        $player_id = $result['player_id'];
        $current_turn_player = $result['current_turn_player'];
        $game_status = $result['status'];
        $has_rolled = $result['has_rolled']; // Check if player has already rolled

        // Check if the game is in progress and if it’s the player’s turn
        if ($game_status !== 'in_progress') {
            echo json_encode(['status' => 'error', 'message' => 'Game is not in progress']);
            exit;
        }
        if ($player_id != $current_turn_player) {
            echo json_encode(['status' => 'error', 'message' => 'It is not your turn']);
            exit;
        }
        if ($has_rolled) {
            echo json_encode(['status' => 'error', 'message' => 'You have already rolled in this turn. Choose an option to advance first.']);
            exit;
        }

        // Roll 4 dice
        $dice = [random_int(1, 6), random_int(1, 6), random_int(1, 6), random_int(1, 6)];

        // Generate possible pairs
        $pairs = [
            'Option 1' => ['pair_a' => $dice[0] + $dice[1], 'pair_b' => $dice[2] + $dice[3]],
            'Option 2' => ['pair_a' => $dice[0] + $dice[2], 'pair_b' => $dice[1] + $dice[3]],
            'Option 3' => ['pair_a' => $dice[0] + $dice[3], 'pair_b' => $dice[1] + $dice[2]]
        ];

        // Save the pairs to the dice_rolls table
        $stmt = $db->prepare("
            INSERT INTO dice_rolls (game_id, player_id, pair_1a, pair_1b, pair_2a, pair_2b, pair_3a, pair_3b)
            VALUES (:game_id, :player_id, :pair_1a, :pair_1b, :pair_2a, :pair_2b, :pair_3a, :pair_3b)
        ");
        $stmt->execute([
            ':game_id' => $game_id,
            ':player_id' => $player_id,
            ':pair_1a' => $pairs['Option 1']['pair_a'],
            ':pair_1b' => $pairs['Option 1']['pair_b'],
            ':pair_2a' => $pairs['Option 2']['pair_a'],
            ':pair_2b' => $pairs['Option 2']['pair_b'],
            ':pair_3a' => $pairs['Option 3']['pair_a'],
            ':pair_3b' => $pairs['Option 3']['pair_b']
        ]);

        // Update player_columns to mark that the player has rolled
        $stmt = $db->prepare("
            UPDATE player_columns 
            SET has_rolled = 1 
            WHERE game_id = :game_id AND player_id = :player_id
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

        // Return the pairs for immediate use
        echo json_encode([
            'status' => 'success',
            'possible_pairs' => $pairs
        ], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        // Return an error message if an exception occurs
        echo json_encode(['status' => 'error', 'message' => 'Failed to process request: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
