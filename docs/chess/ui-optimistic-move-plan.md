# UI Smoothing Plan: Instant Player Move Feedback

## Objective
Make player moves feel immediate, even when backend validation and AI response take longer.

## Current UX Delay Sources
1. The player move is shown only after POST /moves resolves in assets/controllers/chess_board_controller.js.
2. The flow awaits AI autoplay before finishing the move sequence in assets/controllers/chess_board_controller.js.
3. The UI refreshes move list with an extra request after move confirmation in assets/controllers/chess_board_controller.js.

## Target UX
1. Player sees their move on the board instantly after clicking destination.
2. Backend validation remains authoritative.
3. If backend rejects a move, UI rolls back board state and shows an error.
4. AI move runs in background with a visible thinking state, without blocking player feedback.

## Minimal Implementation Plan

### 1. Add Optimistic Move Apply and Rollback in Stimulus Controller
File: assets/controllers/chess_board_controller.js

Actions:
1. Before sending POST /moves, snapshot current game state:
   - game.fen
   - boardState
   - selectedSquare
2. Apply move locally to boardState immediately.
3. Render board immediately.
4. Send POST /moves request.
5. On success:
   - Replace optimistic state with authoritative response via updateGame.
6. On failure:
   - Restore snapshot.
   - Render board.
   - Show API error message.

Notes:
1. Reuse existing move construction methods:
   - buildUciMove
   - buildCastlingMove
2. Keep server as source of truth; optimistic layer is visual only.

### 2. Make AI Autoplay Non-Blocking for Perceived Smoothness
File: assets/controllers/chess_board_controller.js

Actions:
1. In executeMove, do not await AI autoplay.
2. Trigger maybeAutoPlayAi asynchronously after player move success.
3. Add a lightweight state flag, for example isAiThinking.
4. Show and hide AI thinking indicator around ai-move call.

Expected result:
1. Player move appears instantly.
2. UI remains responsive while AI computes.

### 3. Remove Immediate Extra Move-List Fetch After Player Move
Files:
- assets/controllers/chess_board_controller.js
- src/Api/ChessResponseMapper.php

Actions:
1. Use lastMove returned in game payload to append or update list.
2. Keep full GET /moves for:
   - initial load
   - manual refresh
   - recovery after rollback or error paths
3. Optionally keep GET /moves only after AI move to sync full timeline.

## Suggested Data Flow After Change
1. User clicks move.
2. UI applies optimistic board update immediately.
3. POST /moves validates and persists.
4. On success, UI syncs with response game state.
5. AI autoplay starts in background.
6. AI result updates board when ready.

## Error Handling Rules
1. If POST /moves returns 422 INVALID_MOVE:
   - Roll back optimistic board.
   - Show move error.
2. If AI request fails:
   - Keep player move committed.
   - Show non-blocking AI error.
   - Allow manual retry.

## Optional Backend Follow-Up (Not Required for UX Fix)
File: src/Chess/Rules/StockfishRulesEngine.php

Observation:
1. validateMove currently calls three Stockfish operations:
   - listLegalMoves
   - getFenAfterMove
   - inspectPosition

Potential later optimization:
1. Reduce round-trips and process startups by combining checks in fewer engine sessions.
2. This improves true backend response time; optimistic UI improves perceived latency immediately.

## Test Checklist
1. Legal player move appears instantly.
2. Illegal player move rolls back correctly.
3. Castling optimistic move works and rolls back correctly if invalid.
4. Promotion optimistic move works.
5. AI thinking indicator appears after player move confirmation.
6. AI failure does not revert player move.
7. No duplicate moves in move list after sync.
