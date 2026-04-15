# Chess REST API MVP (Symfony)

Local non-commercial chess project built with Symfony, focused on a contract-first backend and a minimal web UI.

Tests and optymalization will be added in near future (most probably)

## Goal
Build a local chess application with:
- REST API for creating games and making moves
- move validation (Hybrid rules)
- persistent game state in SQLite
- AI opponent powered by local Stockfish (no OpenAI API required)
- minimal frontend (Twig + Stimulus)

## Current Status
- [x] Phase 1: Domain and API contract
- [x] Phase 2: Persistence and migrations
- [x] Phase 3: Rules engine and AI move flow
- [x] Phase 4: REST controllers and app services
- [x] Phase 5: Web UI (board, move list, game status)
- [x] Phase 6: Unit/integration/functional tests

## Phase 1 Deliverables
Implemented and validated:
- Domain contract: [docs/chess/phase1-domain-contract.md](docs/chess/phase1-domain-contract.md)
- OpenAPI contract: [docs/chess/openapi.v1.yaml](docs/chess/openapi.v1.yaml)
- Stockfish setup guide: [docs/chess/phase1-stockfish-setup.md](docs/chess/phase1-stockfish-setup.md)
- Runtime diagnostics:
  - API: `GET /api/v1/engine/health`
  - CLI: `php bin/console app:chess:engine:health`

## API Contract Summary (v1)
- `POST /api/v1/games`
- `GET /api/v1/games/{gameId}`
- `POST /api/v1/games/{gameId}/moves`
- `GET /api/v1/games/{gameId}/moves`
- `POST /api/v1/games/{gameId}/ai-move`
- `GET /api/v1/engine/health`

## Planned Phases
### Phase 2: Persistence
- Create Doctrine entities (`Game`, `Move`)
- Add migrations and indexes
- Keep schema aligned with Phase 1 contract

### Phase 3: Rules Engine + AI
- Integrate chess rules validation service
- Integrate Stockfish via UCI for AI move generation
- Return `ENGINE_UNAVAILABLE` when engine is misconfigured

### Phase 4: Application API
- Add controllers, DTOs, validation
- Add consistent JSON error responses
- Keep endpoint behavior aligned with OpenAPI spec

### Phase 5: Minimal UI
- Build chess board page in Twig
- Add Stimulus controller for board interactions
- Show legal/illegal move feedback and game state

### Phase 6: Testing
- Unit tests for rules
- Contract/integration tests for API
- Functional tests for UI flow

## Local Setup
1. Install dependencies:
```bash
composer install
```
2. Configure Stockfish in local env:
- `CHESS_ENGINE=stockfish`
- `CHESS_STOCKFISH_PATH=C:/tools/stockfish/stockfish.exe`
- `CHESS_STOCKFISH_MOVE_TIME_MS=120`
- `CHESS_STOCKFISH_SKILL=6`
3. Verify engine readiness:
```bash
php bin/console app:chess:engine:health
```
4. Start server:
```bash
symfony serve
```

## Notes
- This project is local-only and non-commercial.
- OpenAI API is not required.
- SQLite is the default database for MVP.
