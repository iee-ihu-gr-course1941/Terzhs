<?php
// advance_move.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;
    $option = $_POST['option'] ?? null;

    // Validate input
    if (!$game_id || !$token || !$option) {
        echo json_encode(['status' => 'error', 'message' => 'Game ID, player token, and option are required']);
        exit;
    }

    try {
        // Get player ID using the token
        $stmt = $db->prepare("SELECT id, name FROM players WHERE player_token = :token");
        $stmt->execute([':token' => $token]);
        $player = $stmt->fetch();

        if ($player) {
            $player_id = $player['id'];
            $player_name = $player['name'];

            // Check if the game is in progress
            $stmt = $db->prepare("SELECT current_turn_player, status FROM games WHERE id = :game_id");
            $stmt->execute([':game_id' => $game_id]);
            $game = $stmt->fetch();

            if (!$game) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid game ID']);
                exit;
            }

            if ($game['status'] === 'ended') {
                echo json_encode(['status' => 'error', 'message' => 'The game has already ended']);
                exit;
            }

            if ($game['current_turn_player'] != $player_id) {
                echo json_encode(['status' => 'error', 'message' => 'It is not your turn']);
                exit;
            }

            // Check the number of active markers for the player in this turn
            $stmt = $db->prepare("SELECT COUNT(*) FROM player_columns WHERE game_id = :game_id AND player_id = :player_id AND is_active = 1");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
            $active_markers = $stmt->fetchColumn();

            if ($active_markers >= 3) {
                echo json_encode(['status' => 'error', 'message' => 'You already have 3 active markers. You cannot progress further this turn.']);
                exit;
            }

            // Fetch the latest dice roll for the player in this game
            $stmt = $db->prepare("SELECT pair_1a, pair_1b, pair_2a, pair_2b, pair_3a, pair_3b 
                                  FROM dice_rolls 
                                  WHERE game_id = :game_id AND player_id = :player_id 
                                  ORDER BY roll_time DESC LIMIT 1");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
            $dice_roll = $stmt->fetch();

            if (!$dice_roll) {
                echo json_encode(['status' => 'error', 'message' => 'No dice roll found for this turn']);
                exit;
            }

            // Map options to pairs
            $options_map = [
                1 => [$dice_roll['pair_1a'], $dice_roll['pair_1b']],
                2 => [$dice_roll['pair_2a'], $dice_roll['pair_2b']],
                3 => [$dice_roll['pair_3a'], $dice_roll['pair_3b']],
            ];

            if (!isset($options_map[$option])) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid option selected']);
                exit;
            }

            $selected_pair = $options_map[$option];
            $messages = [];

            // Process each column in the selected pair
            foreach ($selected_pair as $column_number) {
                // Ensure valid column number
                $stmt = $db->prepare("SELECT max_height FROM columns WHERE column_number = :column_number");
                $stmt->execute([':column_number' => $column_number]);
                $column = $stmt->fetch();

                if (!$column) {
                    $messages[] = "Invalid column number: $column_number";
                    continue;
                }

                // Check if the column is already won
                $stmt = $db->prepare("SELECT is_won FROM player_columns WHERE game_id = :game_id AND column_number = :column_number AND is_won = 1");
                $stmt->execute([':game_id' => $game_id, ':column_number' => $column_number]);
                $is_won = $stmt->fetch();

                if ($is_won) {
                    $messages[] = "Column $column_number has already been won and cannot be progressed further";
                    continue;
                }

                // Update or insert the player's marker
                $stmt = $db->prepare("INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active)
                                      VALUES (:game_id, :player_id, :column_number, 1, 1)
                                      ON DUPLICATE KEY UPDATE progress = progress + 1, is_active = 1");
                $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);

                // Check if player reached max height in the column
                $stmt = $db->prepare("SELECT c.max_height, pc.progress FROM columns c
                                      JOIN player_columns pc ON c.column_number = pc.column_number
                                      WHERE pc.game_id = :game_id AND pc.player_id = :player_id AND pc.column_number = :column_number");
                $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);
                $result = $stmt->fetch();

                if ($result) {
                    if ($result['progress'] >= $result['max_height']) {
                        // Mark column as won
                        $stmt = $db->prepare("UPDATE player_columns SET is_active = 0, is_won = 1 WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column_number");
                        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);
                        $messages[] = "Column $column_number is now won!";
                    } else {
                        $messages[] = "Column $column_number progressed to {$result['progress']}. Max height: {$result['max_height']}";
                    }
                }
            }

            // Check how many columns the player has won
            $stmt = $db->prepare("SELECT column_number FROM player_columns WHERE game_id = :game_id AND player_id = :player_id AND is_won = 1");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
            $won_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($won_columns) >= 3) {
                // End the game and declare the winner
                $stmt = $db->prepare("UPDATE games SET status = 'ended' WHERE id = :game_id");
                $stmt->execute([':game_id' => $game_id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => "The game has ended. $player_name has won by claiming columns: " . implode(', ', $won_columns)
                ]);
                exit;
            }

            echo json_encode(['status' => 'success', 'message' => implode('. ', $messages)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
