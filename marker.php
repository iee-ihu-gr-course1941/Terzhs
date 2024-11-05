<?php
// marker.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'];
    $player_id = $_POST['player_id'];
    $column = $_POST['column'];
    $position = $_POST['position'];
    $is_lock = $_POST['is_lock'];

    if ($is_lock) {
        // Lock the column if the player completed it
        $stmt = $db->prepare("UPDATE markers SET is_locked = TRUE WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column' => $column]);
    } else {
        // Advance marker position
        $stmt = $db->prepare("UPDATE markers SET marker_position = :position WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column");
        $stmt->execute([':position' => $position, ':game_id' => $game_id, ':player_id' => $player_id, ':column' => $column]);
    }

    echo json_encode(['status' => 'success']);
}
?>
