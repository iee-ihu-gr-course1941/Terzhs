<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = $_POST['game_id'];
    $token = $_POST['token'];
    $columns = array_map('intval', explode(',', $_POST['columns']));

    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        $player_id = $player['id'];

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT column_number) AS active_markers 
            FROM player_columns 
            WHERE game_id = :game_id AND player_id = :player_id AND is_active = 1
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
        $active_markers = $stmt->fetchColumn();

        if ($active_markers + count($columns) > 3) {
            echo json_encode(['status' => 'error', 'message' => 'You already have 3 active markers']);
            exit;
        }

        foreach ($columns as $column) {
            $stmt = $db->prepare("SELECT progress, max_value FROM player_columns JOIN columns ON player_columns.column_number = columns.column_number WHERE player_id = :player_id AND game_id = :game_id AND column_number = :column");
            $stmt->execute([':player_id' => $player_id, ':game_id' => $game_id, ':column' => $column]);
            $player_column = $stmt->fetch();

            if ($player_column && $player_column['progress'] + 1 >= $player_column['max_value']) {
                echo json_encode(['status' => 'success', 'message' => "Column $column reached the top!"]);
            }

            $stmt = $db->prepare("INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active) VALUES (:game_id, :player_id, :column, 1, 1) ON DUPLICATE KEY UPDATE progress = progress + 1, is_active = 1");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column' => $column]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Advance move applied successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
