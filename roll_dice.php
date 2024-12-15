<?php
// roll_dice.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Retrieve input data
$game_id = $_POST['game_id'] ?? null;
$token = $_POST['token'] ?? null;

// Validate input
if (!$game_id || !$token) {
    echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required']);
    exit;
}

try {
    // Step 1: Fetch player, game, and turn information
    $stmt = $db->prepare("
        SELECT 
            p.id AS player_id, 
            g.current_turn_player, 
            g.status, 
            COALESCE(pc.has_rolled, 0) AS has_rolled
        FROM 
            players p
        JOIN 
            games g ON g.id = :game_id AND (g.player1_id = p.id OR g.player2_id = p.id)
        LEFT JOIN 
            player_columns pc ON pc.game_id = g.id AND pc.player_id = p.id
        WHERE 
            p.player_token = :token
    ");
    $stmt->execute([':game_id' => $game_id, ':token' => $token]);
    $result = $stmt->fetch();

    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token', 'debug' => 'No data fetched from query']);
        exit;
    }

    // Extract data
    $player_id = $result['player_id'];
    $current_turn_player = $result['current_turn_player'];
    $game_status = $result['status'];
    $has_rolled = $result['has_rolled'];

    // Step 2: Validate game state and player turn
    if ($game_status !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'Game is not in progress', 'debug' => ['game_status' => $game_status]]);
        exit;
    }
    if ($player_id != $current_turn_player) {
        echo json_encode(['status' => 'error', 'message' => 'It is not your turn', 'debug' => ['player_id' => $player_id, 'current_turn_player' => $current_turn_player]]);
        exit;
    }
    if ($has_rolled) {
        echo json_encode(['status' => 'error', 'message' => 'You have already rolled in this turn. Choose an option to advance first.', 'debug' => ['has_rolled' => $has_rolled]]);
        exit;
    }

    // Step 3: Mark the player as having rolled
    $stmt = $db->prepare("
        UPDATE player_columns 
        SET has_rolled = 1 
        WHERE game_id = :game_id AND player_id = :player_id
    ");
    $update_result = $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    
    // Debugging database update result
    if (!$update_result) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update player roll status', 'debug' => $stmt->errorInfo()]);
        exit;
    }

    // Step 4: Roll 4 dice
    $dice = [
        random_int(1, 6),
        random_int(1, 6),
        random_int(1, 6),
        random_int(1, 6)
    ];

    // Step 5: Generate possible pairs
    $pairs = [
        'Option 1' => ['pair_a' => $dice[0] + $dice[1], 'pair_b' => $dice[2] + $dice[3]],
        'Option 2' => ['pair_a' => $dice[0] + $dice[2], 'pair_b' => $dice[1] + $dice[3]],
        'Option 3' => ['pair_a' => $dice[0] + $dice[3], 'pair_b' => $dice[1] + $dice[2]]
    ];

    // Debugging dice pairs before inserting into the database
    echo json_encode(['debug' => ['dice' => $dice, 'pairs' => $pairs]]);
    exit;

    // Step 6: Save the dice roll pairs in the database
    $stmt = $db->prepare("
        INSERT INTO dice_rolls 
        (game_id, player_id, pair_1a, pair_1b, pair_2a, pair_2b, pair_3a, pair_3b)
        VALUES 
        (:game_id, :player_id, :pair_1a, :pair_1b, :pair_2a, :pair_2b, :pair_3a, :pair_3b)
    ");
    $insert_result = $stmt->execute([
        ':game_id' => $game_id,
        ':player_id' => $player_id,
        ':pair_1a' => $pairs['Option 1']['pair_a'],
        ':pair_1b' => $pairs['Option 1']['pair_b'],
        ':pair_2a' => $pairs['Option 2']['pair_a'],
        ':pair_2b' => $pairs['Option 2']['pair_b'],
        ':pair_3a' => $pairs['Option 3']['pair_a'],
        ':pair_3b' => $pairs['Option 3']['pair_b']
    ]);
    if (!$insert_result) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save dice roll pairs', 'debug' => $stmt->errorInfo()]);
        exit;
    }

    // Step 7: Respond with the dice roll results
    echo json_encode([
        'status' => 'success',
        'possible_pairs' => $pairs,
        'debug' => ['dice' => $dice]
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // Handle unexpected errors
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred',
        'debug' => $e->getMessage()
    ]);
    exit;
}
?>
