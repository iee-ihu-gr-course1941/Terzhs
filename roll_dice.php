<?php
// roll_dice.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;

    // Validate required inputs
    if (!$game_id || !$token) {
        echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required.']);
        exit;
    }

    try {
        // Fetch player and game information
        $stmt = $db->prepare("
            SELECT 
                p.id AS player_id, 
                g.current_turn_player, 
                g.status,
                COALESCE(dr.has_rolled, 0) AS has_rolled,
                COUNT(pc.is_active) AS active_markers
            FROM players p
            LEFT JOIN games g ON g.id = :game_id
            LEFT JOIN dice_rolls dr ON dr.game_id = g.id AND dr.player_id = p.id
            LEFT JOIN player_columns pc ON pc.game_id = g.id AND pc.player_id = p.id AND pc.is_active = 1
            WHERE p.player_token = :token
        ");
        $stmt->execute([':game_id' => $game_id, ':token' => $token]);
        $result = $stmt->fetch();

        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token.']);
            exit;
        }

        $player_id = $result['player_id'];
        $current_turn_player = $result['current_turn_player'];
        $game_status = $result['status'];
        $has_rolled = $result['has_rolled'];
        $active_markers = $result['active_markers'];

        // Validate game status and turn
        if ($game_status !== 'in_progress') {
            echo json_encode(['status' => 'error', 'message' => 'The game is not currently in progress.']);
            exit;
        }
        if ($player_id != $current_turn_player) {
            echo json_encode(['status' => 'error', 'message' => 'It is not your turn.']);
            exit;
        }

        // Prevent rolling if the player has already rolled and not advanced
        if ($has_rolled) {
            echo json_encode(['status' => 'error', 'message' => 'You have already rolled this turn. Please advance a marker before rolling again.']);
            exit;
        }

        // Roll 4 dice
        $dice = [
            random_int(1, 6),
            random_int(1, 6),
            random_int(1, 6),
            random_int(1, 6)
        ];

        // Generate pairs
        $pairs = [
            ['a' => $dice[0] + $dice[1], 'b' => $dice[2] + $dice[3]],
            ['a' => $dice[0] + $dice[2], 'b' => $dice[1] + $dice[3]],
            ['a' => $dice[0] + $dice[3], 'b' => $dice[1] + $dice[2]]
        ];

        // Check if any pair is valid for advancement
        $valid_pairs = [];
        foreach ($pairs as $index => $pair) {
            foreach ([$pair['a'], $pair['b']] as $column_number) {
                $stmt = $db->prepare("SELECT 1 FROM columns WHERE column_number = :column_number");
                $stmt->execute([':column_number' => $column_number]);
                if ($stmt->fetch()) {
                    $valid_pairs[$index + 1] = $pair;
                    break;
                }
            }
        }

        if (empty($valid_pairs)) {
            // No valid moves, inform the player
            echo json_encode([
                'status' => 'info',
                'message' => 'No valid moves are possible with this roll. Please advance markers or end your turn.',
                'dice' => $dice
            ]);
            exit;
        }

        // Save the roll in the dice_rolls table
        $stmt = $db->prepare("
            INSERT INTO dice_rolls (game_id, player_id, pair_1a, pair_1b, pair_2a, pair_2b, pair_3a, pair_3b, has_rolled)
            VALUES (:game_id, :player_id, :pair_1a, :pair_1b, :pair_2a, :pair_2b, :pair_3a, :pair_3b, 1)
            ON DUPLICATE KEY UPDATE 
                pair_1a = VALUES(pair_1a), pair_1b = VALUES(pair_1b),
                pair_2a = VALUES(pair_2a), pair_2b = VALUES(pair_2b),
                pair_3a = VALUES(pair_3a), pair_3b = VALUES(pair_3b),
                has_rolled = 1
        ");
        $stmt->execute([
            ':game_id' => $game_id,
            ':player_id' => $player_id,
            ':pair_1a' => $pairs[0]['a'],
            ':pair_1b' => $pairs[0]['b'],
            ':pair_2a' => $pairs[1]['a'],
            ':pair_2b' => $pairs[1]['b'],
            ':pair_3a' => $pairs[2]['a'],
            ':pair_3b' => $pairs[2]['b']
        ]);

        // Return the dice roll and valid pairs
        echo json_encode([
            'status' => 'success',
            'message' => 'Dice rolled successfully. Choose a pair to advance your markers.',
            'dice' => [
                'Die 1' => $dice[0],
                'Die 2' => $dice[1],
                'Die 3' => $dice[2],
                'Die 4' => $dice[3]
            ],
            'possible_pairs' => $valid_pairs
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        // Handle exceptions
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
