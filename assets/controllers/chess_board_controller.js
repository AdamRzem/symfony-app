import { Controller } from '@hotwired/stimulus';

const FILES = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];

export default class extends Controller {
    static targets = ['board', 'moves', 'error', 'status', 'result', 'turn', 'fen', 'aiColor'];
    static values = {
        apiBase: String,
    };

    connect() {
        this.game = null;
        this.selectedSquare = null;
        this.boardState = {};
        this.isAiThinking = false;
        this.aiRequestToken = 0;
        this.isMovePending = false;
        this.renderBoard();
        this.setError('');
    }

    async createGame() {
        this.setError('');

        try {
            const aiColor = this.aiColorTarget.value;
            const game = await this.requestJson(this.apiBaseValue, {
                method: 'POST',
                body: JSON.stringify({ aiColor }),
            });

            this.updateGame(game);
            await this.loadMoves();
            this.maybeAutoPlayAi();
        } catch (error) {
            this.handleError(error);
        }
    }

    async onSquareClick(event) {
        if (!this.game || this.isAiThinking || this.isMovePending) {
            if (!this.game) {
                this.setError('Create a game first.');
            }
            return;
        }

        const square = event.currentTarget.dataset.square;
        const piece = this.boardState[square] ?? null;

        if (!this.selectedSquare) {
            if (!piece) {
                return;
            }

            if (!this.pieceBelongsToTurn(piece)) {
                this.setError('Select a piece that belongs to the side to move.');

                return;
            }

            this.selectedSquare = square;
            this.renderBoard();

            return;
        }

        if (this.selectedSquare === square) {
            this.selectedSquare = null;
            this.renderBoard();

            return;
        }

        if (piece && this.pieceBelongsToTurn(piece)) {
            const selectedPiece = this.boardState[this.selectedSquare] ?? '';
            
            // Handle castling attempt (King -> Rook)
            if (selectedPiece.toLowerCase() === 'k' && piece.toLowerCase() === 'r') {
                const move = this.buildCastlingMove(this.selectedSquare, square);
                if (move) {
                    this.executeMove(move);
                    return;
                }
            }

            this.selectedSquare = square;
            this.renderBoard();

            return;
        }

        const move = this.buildUciMove(this.selectedSquare, square);
        this.executeMove(move);
    }

    applyOptimisticMove(uciMove) {
        const fromSquare = uciMove.slice(0, 2);
        const toSquare = uciMove.slice(2, 4);
        const promotion = uciMove.length === 5 ? uciMove[4] : null;
        
        const piece = this.boardState[fromSquare];
        
        // Basic castling
        if (piece && piece.toLowerCase() === 'k') {
            const isWhite = piece === 'K';
            if (fromSquare === 'e1' && toSquare === 'g1') {
                delete this.boardState['h1'];
                this.boardState['f1'] = isWhite ? 'R' : 'r';
            } else if (fromSquare === 'e1' && toSquare === 'c1') {
                delete this.boardState['a1'];
                this.boardState['d1'] = isWhite ? 'R' : 'r';
            } else if (fromSquare === 'e8' && toSquare === 'g8') {
                delete this.boardState['h8'];
                this.boardState['f8'] = isWhite ? 'R' : 'r';
            } else if (fromSquare === 'e8' && toSquare === 'c8') {
                delete this.boardState['a8'];
                this.boardState['d8'] = isWhite ? 'R' : 'r';
            }
        }

        // En passant
        if (piece && piece.toLowerCase() === 'p') {
            const isDiagonalMove = fromSquare[0] !== toSquare[0];
            const isCapture = this.boardState[toSquare] !== undefined;
            if (isDiagonalMove && !isCapture) {
                const capturedPawnSquare = `${toSquare[0]}${fromSquare[1]}`;
                delete this.boardState[capturedPawnSquare];
            }
        }
        
        delete this.boardState[fromSquare];
        
        if (promotion) {
            this.boardState[toSquare] = piece === piece.toUpperCase() ? promotion.toUpperCase() : promotion.toLowerCase();
        } else {
            this.boardState[toSquare] = piece;
        }
        
        this.selectedSquare = null;
        this.renderBoard();
    }

    async executeMove(move) {
        if (this.isMovePending) return;
        this.isMovePending = true;

        const gameIdAtRequestStart = this.game.id;

        // Snapshot
        const snapshot = {
            game: { ...this.game },
            boardState: { ...this.boardState },
            selectedSquare: this.selectedSquare,
        };
        
        this.applyOptimisticMove(move);

        try {
            const game = await this.requestJson(`${this.apiBaseValue}/${this.game.id}/moves`, {
                method: 'POST',
                body: JSON.stringify({ uciMove: move }),
            });

            if (this.game && this.game.id !== gameIdAtRequestStart) return;

            this.setError('');
            this.updateGame(game);
            this.maybeAutoPlayAi();
        } catch (error) {
            if (this.game && this.game.id !== gameIdAtRequestStart) return;

            // Restore snapshot
            this.game = snapshot.game;
            this.boardState = snapshot.boardState;
            this.selectedSquare = snapshot.selectedSquare;
            this.renderBoard();
            
            this.handleError(error);
            
            // Resync game state after ambiguous failures
            try {
                const syncedGame = await this.requestJson(`${this.apiBaseValue}/${this.game.id}`);
                if (this.game && this.game.id === gameIdAtRequestStart) {
                    this.updateGame(syncedGame);
                    await this.loadMoves();
                }
            } catch (syncError) {
                // Ignore sync errors
            }
        } finally {
            this.isMovePending = false;
        }
    }

    async maybeAutoPlayAi() {
        if (!this.game) {
            return;
        }

        if (!['in_progress', 'check'].includes(this.game.status)) {
            return;
        }

        if (this.game.turn !== this.game.aiColor) {
            return;
        }

        const currentToken = ++this.aiRequestToken;
        this.isAiThinking = true;
        this.statusTarget.textContent = 'AI is thinking...';
        
        const gameIdAtRequestStart = this.game.id;

        try {
            const game = await this.requestJson(`${this.apiBaseValue}/${this.game.id}/ai-move`, {
                method: 'POST',
            });
            
            if (this.game && this.game.id !== gameIdAtRequestStart) return;
            
            this.updateGame(game);
        } catch (error) {
            if (this.game && this.game.id !== gameIdAtRequestStart) return;
            
            this.setError('AI error. Try moving manually or restart game.');
            this.statusTarget.textContent = this.game.status;
        } finally {
            if (this.aiRequestToken === currentToken) {
                this.isAiThinking = false;
            }
        }
    }

    async loadMoves() {
        if (!this.game) {
            this.movesTarget.innerHTML = '';
            this.movesTarget.scrollTop = 0;

            return;
        }

        const expectedGameId = this.game.id;
        const payload = await this.requestJson(`${this.apiBaseValue}/${this.game.id}/moves`);
        
        if (this.game && this.game.id !== expectedGameId) return;

        const moves = payload.moves ?? [];

        if (moves.length === 0) {
            this.movesTarget.innerHTML = '<li>No moves yet.</li>';
            this.movesTarget.scrollTop = this.movesTarget.scrollHeight;

            return;
        }

        this.movesTarget.innerHTML = moves
            .map((move) => `<li>#${move.ply} ${move.uci}${move.isCheckmate ? ' (mate)' : move.isCheck ? ' (check)' : ''}</li>`)
            .join('');
        this.movesTarget.scrollTop = this.movesTarget.scrollHeight;
    }

    updateGame(game) {
        this.game = game;
        this.boardState = this.parseFen(game.fen);
        this.turnTarget.textContent = game.turn;
        
        this.statusTarget.textContent = game.status;
        
        this.resultTarget.textContent = game.result;
        this.fenTarget.textContent = `FEN: ${game.fen}`;
        this.selectedSquare = null;
        this.renderBoard();

        if (game.lastMove) {
            this.appendMove(game.lastMove);
        }
    }

    appendMove(move) {
        if (!move) return;
        
        if (this.movesTarget.innerHTML.includes('No moves yet.')) {
            this.movesTarget.innerHTML = '';
        }

        const lastLi = this.movesTarget.lastElementChild;
        if (lastLi && lastLi.textContent.startsWith(`#${move.ply} `)) {
            return;
        }

        const newHtml = `<li>#${move.ply} ${move.uci}${move.isCheckmate ? ' (mate)' : move.isCheck ? ' (check)' : ''}</li>`;
        this.movesTarget.insertAdjacentHTML('beforeend', newHtml);
        this.movesTarget.scrollTop = this.movesTarget.scrollHeight;
    }

    renderBoard() {
        const squares = [];

        for (let rank = 8; rank >= 1; rank -= 1) {
            for (let fileIndex = 0; fileIndex < FILES.length; fileIndex += 1) {
                const file = FILES[fileIndex];
                const square = `${file}${rank}`;
                const piece = this.boardState[square] ?? '';
                const selectedClass = this.selectedSquare === square ? ' selected' : '';
                const pieceAssetName = piece ? this.pieceAssetName(piece) : '';
                const pieceName = piece ? this.pieceName(piece) : 'empty';
                const pieceMarkup = piece
                    ? `<span class="chess-piece"><img class="chess-piece-image" src="/chess/pieces/maestro/${pieceAssetName}.svg" alt="${pieceName}" draggable="false"></span>`
                    : '';

                squares.push(`
                    <button
                        type="button"
                        class="chess-square${selectedClass}"
                        data-action="click->chess-board#onSquareClick"
                        data-square="${square}"
                        aria-label="${square} ${pieceName}">
                        ${pieceMarkup}
                    </button>
                `);
            }
        }

        this.boardTarget.innerHTML = squares.join('');
    }

    parseFen(fen) {
        const [boardPart] = fen.split(' ');
        const ranks = boardPart.split('/');
        const board = {};

        for (let rankIndex = 0; rankIndex < ranks.length; rankIndex += 1) {
            const rank = 8 - rankIndex;
            let fileIndex = 0;

            for (const char of ranks[rankIndex]) {
                if (/\d/.test(char)) {
                    fileIndex += Number.parseInt(char, 10);
                    continue;
                }

                const file = FILES[fileIndex];
                board[`${file}${rank}`] = char;
                fileIndex += 1;
            }
        }

        return board;
    }

    pieceBelongsToTurn(piece) {
        if (!this.game) {
            return false;
        }

        if (this.game.turn === 'white') {
            return piece === piece.toUpperCase();
        }

        return piece === piece.toLowerCase();
    }

    pieceAssetName(piece) {
        const isWhite = piece === piece.toUpperCase();

        return `${isWhite ? 'w' : 'b'}${piece.toUpperCase()}`;
    }

    pieceName(piece) {
        const isWhite = piece === piece.toUpperCase();
        const pieceNames = {
            K: 'king',
            Q: 'queen',
            R: 'rook',
            B: 'bishop',
            N: 'knight',
            P: 'pawn',
        };

        return `${isWhite ? 'white' : 'black'} ${pieceNames[piece.toUpperCase()] ?? 'piece'}`;
    }

    buildCastlingMove(kingSquare, rookSquare) {
        if (kingSquare === 'e1' && rookSquare === 'h1') return 'e1g1';
        if (kingSquare === 'e1' && rookSquare === 'a1') return 'e1c1';
        if (kingSquare === 'e8' && rookSquare === 'h8') return 'e8g8';
        if (kingSquare === 'e8' && rookSquare === 'a8') return 'e8c8';

        return null;
    }

    buildUciMove(fromSquare, toSquare) {
        const movingPiece = this.boardState[fromSquare] ?? '';

        if (!movingPiece) {
            return `${fromSquare}${toSquare}`;
        }

        const isPawn = movingPiece.toLowerCase() === 'p';
        const targetRank = Number.parseInt(toSquare[1], 10);
        const isPromotion = isPawn && (targetRank === 1 || targetRank === 8);

        if (!isPromotion) {
            return `${fromSquare}${toSquare}`;
        }

        return `${fromSquare}${toSquare}q`;
    }

    async requestJson(url, options = {}) {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            ...options,
        });

        const text = await response.text();
        const payload = text ? JSON.parse(text) : {};

        if (!response.ok) {
            throw payload;
        }

        return payload;
    }

    handleError(error) {
        const message = error?.error?.message ?? 'Unexpected API error.';
        this.setError(message);
    }

    setError(message) {
        this.errorTarget.textContent = message;
    }
}