# Cant Stop - PHP/MySQL Web API

Link to the Application: [Cant Stop Game](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/)

## Project Title

Cant Stop - PHP/MySQL Web API

## Project Description

This project implements the board game "Cant Stop" using a REST API in PHP/MySQL.

### Files and Their Purpose

- **db\_connect.php**: Connects to the MySQL database.
- **player.php**: Registers a new player.
- **game.php**: Creates or joins a game.
- **roll\_dice.php**: Handles dice rolling logic.
- **advance.php**: Advances columns.
- **stop.php**: Merges temporary progress and ends the turn.
- **scoreboard.php**: Displays the scoreboard and statistics.

Players can register, create/join a game, roll dice, advance or stop their markers according to the Cant Stop game logic, and view scores.

## API Description

### 1. Register Player

- **File**: `player.php`
- **Method**: `POST`
- **Purpose**: Creates a new player and returns a unique token.
- **URL**: [Register Player](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/player.php)
- **Parameters**:
  - `name` (string): The name of the player.
- **Possible Scenarios**:
  - **Success** (when a valid name is provided):
    ```json
    {
      "status": "success",
      "player_token": "abc123..."
    }
    ```
  - **Error** (when the name is not provided):
    ```json
    {
      "status": "error",
      "message": "Player name is required"
    }
    ```
  - **Error** (database connection failure):
    ```json
    {
      "status": "error",
      "message": "Failed to register player: Database connection failed"
    }
    ```
  - **Error** (other exceptions):
    ```json
    {
      "status": "error",
      "message": "Failed to register player: [error details]"
    }
    ```

### 2. Create / Join Game

- **File**: `game.php`
- **Method**: `POST`
- **Purpose**:
  - `create`: Creates a new game.
  - `join`: Joins an existing game.
- **URL**: [Create / Join Game](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/game.php)
- **Parameters**:
  - `action` (string): Either `create` or `join`.
  - `token` (string): The token of the player.
- **Possible Scenarios**:

#### Create

- **Action**: `create`
- **Scenarios**:
  1. **Success** (when a game is successfully created):
     ```json
     {
       "status": "success",
       "game_id": 32,
       "game_status": "waiting"
     }
     ```
  2. **Error** (when the token is missing):
     ```json
     {
       "status": "error",
       "message": "Player token is required"
     }
     ```
  3. **Error** (when the token is invalid):
     ```json
     {
       "status": "error",
       "message": "Invalid player token"
     }
     ```
  4. **Error** (when action is missing):
     ```json
     {
       "status": "error",
       "message": "Action is required"
     }
     ```
  5. **Error** (unexpected issue):
     ```json
     {
       "status": "error",
       "message": "Failed to process request: [error details]"
     }
     ```
  6. **Error** (invalid request method):
     ```json
     {
       "status": "error",
       "message": "Invalid request method"
     }
     ```

#### Join

- **Action**: `join`
- **Scenarios**:
  1. **Success** (when the player successfully joins a game):
     ```json
     {
       "status": "success",
       "game_id": 32,
       "game_status": "in_progress"
     }
     ```
  2. **Error** (when no games are available to join):
     ```json
     {
       "status": "error",
       "message": "No available game to join"
     }
     ```
  3. **Error** (when the token is invalid):
     ```json
     {
       "status": "error",
       "message": "Invalid player token"
     }
     ```
  4. **Error** (when action is missing):
     ```json
     {
       "status": "error",
       "message": "Action is required"
     }
     ```
  5. **Error** (unexpected issue):
     ```json
     {
       "status": "error",
       "message": "Failed to process request: [error details]"
     }
     ```
  6. **Error** (invalid request method):
     ```json
     {
       "status": "error",
       "message": "Invalid request method"
     }
     ```

### 3. Roll Dice

- **File**: `roll_dice.php`
- **Method**: `POST`
- **Purpose**: Handles the logic for rolling dice, determining possible pairs, and checking validity.
- **URL**: [Roll Dice](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/roll_dice.php)
- **Parameters**:
  - `game_id` (int): The ID of the game.
  - `token` (string): The token of the player.
- **Possible Scenarios**:
  1. **Success** (valid pairs available):
     ```json
     {
       "status": "success",
       "message": "Dice rolled successfully. Choose a pair to advance.",
       "dice": {
         "Die1": 3,
         "Die2": 5,
         "Die3": 2,
         "Die4": 6
       },
       "pairs": [
         {"option": 1, "a": 8, "b": 8, "valid": "valid"},
         {"option": 2, "a": 9, "b": 7, "valid": "valid"},
         {"option": 3, "a": 7, "b": 10, "valid": "invalid"}
       ]
     }
     ```
  2. **Bust** (no valid pairs):
     ```json
     {
       "status": "info",
       "message": "Bust! No valid columns available to place markers. Turn passes to the other player.",
       "dice": [3,5,2,6],
       "pairs": [
         {"option": 1, "a": 8, "b": 8, "valid": "invalid"},
         {"option": 2, "a": 9, "b": 7, "valid": "invalid"},
         {"option": 3, "a": 7, "b": 10, "valid": "invalid"}
       ]
     }
     ```
  3. **Error** (invalid parameters):
     ```json
     {
       "status": "error",
       "message": "Game ID and player token are required."
     }
     ```
  4. **Error** (already rolled):
     ```json
     {
       "status": "error",
       "message": "You have already rolled this turn. Please advance a marker before rolling again."
     }
     ```
  5. **Error** (game not in progress):
     ```json
     {
       "status": "error",
       "message": "Game is not currently in progress."
     }
     ```
  6. **Error** (not player’s turn):
     ```json
     {
       "status": "error",
       "message": "It is not your turn."
     }
     ```
  7. **Error** (invalid game ID or player token):
     ```json
     {
       "status": "error",
       "message": "Invalid game ID or player token."
     }
     ```
  8. **Error** (unexpected issue):
     ```json
     {
       "status": "error",
       "message": "An error occurred: [error details]"
     }
     ```
  9. **Error** (invalid request method):
     ```json
     {
       "status": "error",
       "message": "Invalid request method."
     }
     ```

### 4. Advance Columns

- **File**: `advance.php`
- **Method**: `POST`
- **Purpose**: Advances columns based on the chosen dice pair option. The `option` parameter determines which pair of dice sums will be used to update the progress for the columns.
- **URL**: [Advance Columns](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/advance.php)
- **Parameters**:
  - `game_id` (int): The ID of the game.
  - `token` (string): The token of the player.
  - `option` (int): The chosen option for advancing (1, 2, or 3).
- **Possible Scenarios**:

1. **Success** (advances markers successfully):
   ```json
   {
     "status": "success",
     "message": "Advanced temporary marker on column 5. Column 8 is now won and cannot be advanced further."
   }
   ```
2. **Error** (invalid request method):
   ```json
   {
     "status": "error",
     "message": "Invalid request method."
   }
   ```
3. **Error** (missing or invalid parameters):
   ```json
   {
     "status": "error",
     "message": "Game ID, player token, and option are required."
   }
   ```
4. **Error** (game not in progress):
   ```json
   {
     "status": "error",
     "message": "The game is not in progress."
   }
   ```
5. **Error** (not player’s turn):
   ```json
   {
     "status": "error",
     "message": "It is not your turn."
   }
   ```
6. **Error** (no dice roll found):
   ```json
   {
     "status": "error",
     "message": "No dice roll found. You must roll before advancing."
   }
   ```
7. **Error** (invalid option):
   ```json
   {
     "status": "error",
     "message": "Invalid pair option."
   }
   ```
8. **Error** (no valid columns advanced):
   ```json
   {
     "status": "error",
     "message": "No valid columns were advanced. Column 10 has already been won. Skipping. This may occur due to a game state limitation."
   }
   ```
9. **Success** (game won):
   ```json
   {
     "status": "success",
     "message": "You have won the game with columns 5, 8, and 10! Congratulations!"
   }
   ```
10. **Error** (invalid game ID or token):
    ```json
    {
      "status": "error",
      "message": "Invalid game ID or token."
    }
    ```
11. **Error** (unexpected issue):
    ```json
    {
      "status": "error",
      "message": "An error occurred: [error details]"
    }
    ```

### 5. Stop Turn

- **File**: [`stop.php`](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/stop.php)
- **Method**: `POST`
- **Purpose**: Locks in temporary progress, finalizes the player's turn, and switches to the next player. This ensures any progress made during the turn is retained, affecting subsequent turns and the overall game state.
- **URL**: [Stop Turn](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/stop.php)
- **Parameters**:
  - `game_id` (int): The ID of the game.
  - `token` (string): The token of the player.
- **Possible Scenarios**:

1. **Success** (turn ended successfully):
   ```json
   {
     "status": "success",
     "message": "Column 5 progress increased to 4. Column 8 is now won by you. Turn ended successfully."
   }
   ```
2. **Error** (invalid request method):
   ```json
   {
     "status": "error",
     "message": "Invalid request method."
   }
   ```
3. **Error** (missing or invalid parameters):
   ```json
   {
     "status": "error",
     "message": "Game ID and player token are required."
   }
   ```
4. **Error** (game not in progress):
   ```json
   {
     "status": "error",
     "message": "The game is not in progress."
   }
   ```
5. **Error** (not player’s turn):
   ```json
   {
     "status": "error",
     "message": "It is not your turn."
   }
   ```
6. **Error** (unexpected database issue):
   ```json
   {
     "status": "error",
     "message": "An error occurred: [error details]"
   }
   ```
7. **Error** (invalid player token):
   ```json
   {
     "status": "error",
     "message": "Invalid player token."
   }
   ```
8. **Error** (invalid game ID):
   ```json
   {
     "status": "error",
     "message": "Invalid game ID."
   }
   ```

### 6. View Scoreboard

- **File**: `scoreboard.php`
- **Method**: `GET`
- **Purpose**: Displays the scoreboard for all games or a specific game, depending on the parameters provided. It allows users to view the leaderboard or the performance in a single game.
- **URL**: [View Scoreboard](https://users.it.teithe.gr/~iee2020168/ADISE24_Cant-Stop/scoreboard.php)
- **Parameters**:
  - `mode` (string, optional): If set to `all`, retrieves the global scoreboard.
  - `game_id` (int, optional): Specifies the game ID to retrieve the scoreboard for a particular game.
- **Possible Scenarios**:

1. **Success** (global scoreboard retrieved):
   ```json
   {
     "status": "success",
     "mode": "all",
     "scoreboard": [
       {"player_id": 1, "player_name": "Alice", "total_wins": 5},
       {"player_id": 2, "player_name": "Bob", "total_wins": 3}
     ]
   }
   ```

2. **Success** (specific game scoreboard retrieved):
   ```json
   {
     "status": "success",
     "mode": "single_game",
     "game_id": 12,
     "game_status": "completed",
     "winner_id": 1,
     "scoreboard": [
       {"player_id": 1, "player_name": "Alice", "columns_won": 3},
       {"player_id": 2, "player_name": "Bob", "columns_won": 1}
     ]
   }
   ```

3. **Error** (invalid request method):
   ```json
   {
     "status": "error",
     "message": "Invalid request method (only GET is allowed)."
   }
   ```

4. **Error** (invalid game ID):
   ```json
   {
     "status": "error",
     "message": "Invalid game ID."
   }
   ```

5. **Error** (missing parameters):
   ```json
   {
     "status": "error",
     "message": "Please provide either mode=all or game_id=?"
   }
   ```

6. **Error** (unexpected database issue):
   ```json
   {
     "status": "error",
     "message": "Database error: [error details]"
   }
   ```

