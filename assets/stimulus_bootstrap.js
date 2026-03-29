import { startStimulusApp } from '@symfony/stimulus-bundle';
import CounterController from './controllers/counter_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
app.register('counter', CounterController);
