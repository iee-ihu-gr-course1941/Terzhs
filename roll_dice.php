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
        echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required']);
        exit;
    }

    try {
        // Fetch player and game information
        $stmt = $db->prepare("
            SELECT 
                p.id AS player_id, 
                g.current_turn_player, 
                g.status,
                COALESCE(pc.has_rolled, 0) AS has_rolled
            FROM players p
            JOIN games g ON g.id = :game_id
            LEFT JOIN player_columns pc ON pc.game_id = g.id AND pc.player_id = p.id
            WHERE p.player_token = :token
        ");
        $stmt->execute([':game_id' => $game_id, ':token' => $token]);
        $result = $stmt->fetch();

        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token']);
            exit;
        }

        $player_id = $result['player_id'];
        $current_turn_player = $result['current_turn_player'];
        $game_status = $result['status'];
        $has_rolled = $result['has_rolled'];

        // Validate game status and turn
        if ($game_status !== 'in_progress') {
            echo json_encode(['status' => 'error', 'message' => 'The game is not in progress']);
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

        // Generate pairs
        $pairs = [
            'Option 1' => ['pair_a' => $dice[0] + $dice[1], 'pair_b' => $dice[2] + $dice[3]],
            'Option 2' => ['pair_a' => $dice[0] + $dice[2], 'pair_b' => $dice[1] + $dice[3]],
            'Option 3' => ['pair_a' => $dice[0] + $dice[3], 'pair_b' => $dice[1] + $dice[2]]
        ];

        // Save the roll in the dice_rolls table
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

        // Mark that the player has rolled and insert column_number = 0 as a placeholder
        $stmt = $db->prepare("
            INSERT INTO player_columns (game_id, player_id, has_rolled, column_number) 
            VALUES (:game_id, :player_id, 1, 0)
            ON DUPLICATE KEY UPDATE has_rolled = 1
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

        // Return the dice roll options
        echo json_encode([
            'status' => 'success',
            'dice' => $dice,
            'possible_pairs' => $pairs
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        // Handle exceptions
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
