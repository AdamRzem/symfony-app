# Phase 1 Stockfish Setup (Local)

This setup keeps the project local and non-commercial, with no OpenAI API dependency.

## 1. Install Stockfish
- Download a Windows binary from the official Stockfish project.
- Place the executable in a stable path, for example `C:/tools/stockfish/stockfish.exe`.

## 2. Configure environment
Set the following variables in local environment files:
- `CHESS_ENGINE=stockfish`
- `CHESS_STOCKFISH_PATH=C:/tools/stockfish/stockfish.exe`
- `CHESS_STOCKFISH_MOVE_TIME_MS=120`
- `CHESS_STOCKFISH_SKILL=6`

## 3. Validate readiness
Run:

```bash
php bin/console app:chess:engine:health
```

Expected successful output includes:
- `engine: stockfish`
- `status: ready`
- `version: <stockfish version>`

If status is `unavailable`, check binary path and executable permissions.

## 4. Contract impact
- `POST /api/v1/games/{gameId}/ai-move` may return `503 ENGINE_UNAVAILABLE`.
- `GET /api/v1/engine/health` reports whether engine is ready.
