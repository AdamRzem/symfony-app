import { startStimulusApp } from '@symfony/stimulus-bundle';
import CounterController from './controllers/counter_controller.js';
import ChessBoardController from './controllers/chess_board_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
app.register('counter', CounterController);
app.register('chess-board', ChessBoardController);
