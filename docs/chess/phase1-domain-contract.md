# Chess MVP Phase 1 Domain Contract

This document freezes the domain and API contract for local non-commercial MVP.

## Scope
- Mode: player vs AI
- Deployment: local only
- Security: public API in MVP
- Database: SQLite
- AI engine: local Stockfish binary via UCI protocol
- API versioning: /api/v1
- Move notation in requests: UCI (example: e2e4)
- AI flow: separate endpoint (/ai-move)
- Board payload: FEN only

## Ubiquitous Language
- Game: one chess match from initial setup to terminal state
- Move: one legal action applied to a game state
- Ply: half-move index (white move = ply 1, black move = ply 2, etc.)
- Turn: active side (white or black)
- FEN: current board state string

## Aggregate: Game
- id: UUID string
- fen: current board state in FEN
- turn: white|black
- status: in_progress|check|checkmate|stalemate|draw|resigned
- result: white_win|black_win|draw|ongoing
- ai_color: white|black
- created_at: datetime
- updated_at: datetime

## Entity: Move
- id: UUID string
- game_id: Game reference
- ply: integer
- uci: string (for example e2e4)
- promotion: q|r|b|n|null
- san: string|null
- fen_after: FEN after move is applied
- is_check: bool
- is_checkmate: bool
- created_at: datetime

## Domain Invariants
- No move can be applied if game status is terminal.
- A move must be legal in current position.
- The move side must match game turn.
- AI move is legal and uses the same validation pipeline.
- Every persisted move must store fen_after.
- Each new move increments ply by exactly 1.

## Hybrid Rules for MVP
Included:
- legal piece movement
- castling
- en passant
- promotion
- check/checkmate/stalemate detection

Excluded in MVP:
- fifty-move draw rule
- threefold repetition draw rule

## Error Catalog
- INVALID_MOVE -> HTTP 422
- GAME_FINISHED -> HTTP 409
- GAME_NOT_FOUND -> HTTP 404
- BAD_PAYLOAD -> HTTP 400
- ENGINE_UNAVAILABLE -> HTTP 503
- ENGINE_FAILURE -> HTTP 500

## Stockfish Configuration Contract
- CHESS_ENGINE must be set to stockfish in MVP.
- CHESS_STOCKFISH_PATH must point to a local executable file.
- CHESS_STOCKFISH_MOVE_TIME_MS defines target think time for AI move selection.
- CHESS_STOCKFISH_SKILL defines engine strength on a 0-20 scale.
- Engine health is exposed as a diagnostic command and API contract endpoint.

## Acceptance for Phase 1
- Domain model names and invariants are frozen.
- API endpoints, request/response schema and error codes are frozen.
- Contract is represented in OpenAPI file and covered by contract test.
- Stockfish integration mode and env contract are frozen.
