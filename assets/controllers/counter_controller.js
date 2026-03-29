import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['value'];
    static values = {
        initialValue: Number,
    };

    connect() {
        this.count = this.hasInitialValueValue ? this.initialValueValue : 0;
        this.renderCount();
    }

    increment() {
        this.count += 1;
        this.renderCount();
    }

    decrement() {
        this.count -= 1;
        this.renderCount();
    }

    reset() {
        this.count = this.hasInitialValueValue ? this.initialValueValue : 0;
        this.renderCount();
    }

    renderCount() {
        this.valueTarget.textContent = String(this.count);
    }
}
