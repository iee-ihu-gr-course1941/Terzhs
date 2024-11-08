<?php
// advance.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'];
    $token = $_POST['token'];
    $column = $_POST['column'];

    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        $stmt = $db->prepare("INSERT INTO markers (game_id, player_id, column_number) VALUES (:game_id, :player_id, :column) ON DUPLICATE KEY UPDATE marker_position = marker_position + 1");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player['id'], ':column' => $column]);

        echo json_encode(['status' => 'success', 'message' => 'Marker advanced.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
