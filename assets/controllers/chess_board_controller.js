import { Controller } from '@hotwired/stimulus';

const FILES = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];

export default class extends Controller {
    static targets = ['board', 'moves', 'error', 'status', 'result', 'turn', 'gameId', 'fen', 'aiMoveButton', 'aiColor'];
    static values = {
        apiBase: String,
    };

    connect() {
        this.game = null;
        this.selectedSquare = null;
        this.boardState = {};
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

            // If AI is configured as white, user can ask AI to make the opening move.
            if (this.game && this.game.turn === this.game.aiColor) {
                this.aiMoveButtonTarget.disabled = false;
            }
        } catch (error) {
            this.handleError(error);
        }
    }

    async makeAiMove() {
        if (!this.game) {
            this.setError('Create a game first.');

            return;
        }

        this.setError('');

        try {
            const game = await this.requestJson(`${this.apiBaseValue}/${this.game.id}/ai-move`, {
                method: 'POST',
            });

            this.updateGame(game);
            await this.loadMoves();
        } catch (error) {
            this.handleError(error);
        }
    }

    async onSquareClick(event) {
        if (!this.game) {
            this.setError('Create a game first.');

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
            this.selectedSquare = square;
            this.renderBoard();

            return;
        }

        const move = this.buildUciMove(this.selectedSquare, square);
        this.selectedSquare = null;
        this.renderBoard();

        try {
            const game = await this.requestJson(`${this.apiBaseValue}/${this.game.id}/moves`, {
                method: 'POST',
                body: JSON.stringify({ uciMove: move }),
            });

            this.setError('');
            this.updateGame(game);
            await this.loadMoves();
        } catch (error) {
            this.handleError(error);
        }
    }

    async loadMoves() {
        if (!this.game) {
            this.movesTarget.innerHTML = '';

            return;
        }

        const payload = await this.requestJson(`${this.apiBaseValue}/${this.game.id}/moves`);
        const moves = payload.moves ?? [];

        if (moves.length === 0) {
            this.movesTarget.innerHTML = '<li>No moves yet.</li>';

            return;
        }

        this.movesTarget.innerHTML = moves
            .map((move) => `<li>#${move.ply} ${move.uci}${move.isCheckmate ? ' (mate)' : move.isCheck ? ' (check)' : ''}</li>`)
            .join('');
    }

    updateGame(game) {
        this.game = game;
        this.boardState = this.parseFen(game.fen);
        this.gameIdTarget.textContent = game.id;
        this.turnTarget.textContent = game.turn;
        this.statusTarget.textContent = game.status;
        this.resultTarget.textContent = game.result;
        this.fenTarget.textContent = `FEN: ${game.fen}`;
        this.aiMoveButtonTarget.disabled = game.turn !== game.aiColor;
        this.selectedSquare = null;
        this.renderBoard();
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
