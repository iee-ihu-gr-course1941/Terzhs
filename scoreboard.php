<?php
// scoreboard.php
require 'db_connect.php';

// Return JSON
header('Content-Type: application/json');

// Only allow GET in this example
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'status' => 'error',
        'message'=> 'Invalid request method (only GET is allowed).'
    ]);
    exit;
}

// We check query params: e.g. scoreboard.php?mode=all or scoreboard.php?game_id=1
$mode    = $_GET['mode']    ?? null;
$game_id = $_GET['game_id'] ?? null;

try {
    // If the user wants the scoreboard for ALL games (global scoreboard):
    if ($mode === 'all') {
        // 1) Query total wins across ALL completed games
        //    A "win" is a completed game where 'winner_id' = p.id
        $stmt = $db->prepare("
            SELECT 
                p.id AS player_id,
                p.name AS player_name,
                COUNT(g.id) AS total_wins
            FROM games g
            JOIN players p ON g.winner_id = p.id
            WHERE g.status = 'completed'
            GROUP BY p.id
            ORDER BY total_wins DESC, player_name
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build the scoreboard array
        $scoreboard = [];
        foreach ($results as $row) {
            $scoreboard[] = [
                'player_id'   => (int)$row['player_id'],
                'player_name' => $row['player_name'],
                'total_wins'  => (int)$row['total_wins']
            ];
        }

        // Return a JSON response
        echo json_encode([
            'status'     => 'success',
            'mode'       => 'all',
            'scoreboard' => $scoreboard
        ]);
        exit;

    } elseif ($game_id) {
        // 2) Scoreboard for ONE specific game
        //    First, check if the game exists
        $stmtGame = $db->prepare("
            SELECT id, status, winner_id
            FROM games
            WHERE id = :game_id
        ");
        $stmtGame->execute([':game_id' => $game_id]);
        $game = $stmtGame->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid game ID.'
            ]);
            exit;
        }

        // Now, gather scoreboard details
        // e.g. how many columns each player has won in this game
        $stmtPlayers = $db->prepare("
            SELECT 
                p.id AS player_id,
                p.name AS player_name,
                COUNT(CASE WHEN pc.is_won = 1 THEN 1 END) AS columns_won
            FROM players p
            JOIN player_columns pc ON pc.player_id = p.id
            WHERE pc.game_id = :game_id
            GROUP BY p.id
            ORDER BY columns_won DESC, p.id
        ");
        $stmtPlayers->execute([':game_id' => $game_id]);
        $playersData = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);

        // Build scoreboard array
        $scoreboard = [];
        foreach ($playersData as $pd) {
            $scoreboard[] = [
                'player_id'   => (int)$pd['player_id'],
                'player_name' => $pd['player_name'],
                'columns_won' => (int)$pd['columns_won']
            ];
        }

        // Return JSON
        // You can also include 'game_status', 'winner_id' if desired
        echo json_encode([
            'status'      => 'success',
            'mode'        => 'single_game',
            'game_id'     => $game['id'],
            'game_status' => $game['status'],
            'winner_id'   => $game['winner_id'] ?: null,
            'scoreboard'  => $scoreboard
        ]);
        exit;

    } else {
        // If neither ?mode=all nor ?game_id=..., return an error
        echo json_encode([
            'status'  => 'error',
            'message' => 'Please provide either mode=all or game_id=?'
        ]);
        exit;
    }

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
