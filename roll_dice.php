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
            SELECT p.id as player_id, g.current_turn_player, g.status 
            FROM players p 
            JOIN games g ON g.id = :game_id
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

        // Check if the game is in progress and if it’s the player’s turn
        if ($game_status !== 'in_progress') {
            echo json_encode(['status' => 'error', 'message' => 'Game is not in progress']);
            exit;
        }
        if ($player_id != $current_turn_player) {
            echo json_encode(['status' => 'error', 'message' => 'It is not your turn']);
            exit;
        }

        // Roll 4 dice and generate possible pairs
        $dice = [random_int(1, 6), random_int(1, 6), random_int(1, 6), random_int(1, 6)];
        $pairs = [
            ['pair_1' => $dice[0] + $dice[1], 'pair_2' => $dice[2] + $dice[3], 'description' => "Pair 1: Dice 1 + Dice 2, Pair 2: Dice 3 + Dice 4"],
            ['pair_1' => $dice[0] + $dice[2], 'pair_2' => $dice[1] + $dice[3], 'description' => "Pair 1: Dice 1 + Dice 3, Pair 2: Dice 2 + Dice 4"],
            ['pair_1' => $dice[0] + $dice[3], 'pair_2' => $dice[1] + $dice[2], 'description' => "Pair 1: Dice 1 + Dice 4, Pair 2: Dice 2 + Dice 3"]
        ];

        // Check if there are valid pairs
        if (empty($pairs)) {
            echo json_encode(['status' => 'error', 'message' => 'No valid pairs generated']);
            exit;
        }

        // Return dice rolls and possible pairs
        echo json_encode([
            'status' => 'success',
            'dice_rolls' => [
                'dice_1' => $dice[0],
                'dice_2' => $dice[1],
                'dice_3' => $dice[2],
                'dice_4' => $dice[3]
            ],
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
