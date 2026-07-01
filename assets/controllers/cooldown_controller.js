import { Controller } from '@hotwired/stimulus';

/*
 * Compte à rebours avant de pouvoir renvoyer l'email de confirmation.
 * Le temps restant est fourni par le serveur (source de vérité), on ne fait
 * qu'afficher le décompte et réactiver le bouton une fois arrivé à zéro.
 */
export default class extends Controller {
    static targets = ['label', 'button'];
    static values = { remaining: Number };

    connect() {
        this.remaining = this.remainingValue;
        if (this.remaining > 0) {
            this.render();
            this.interval = setInterval(() => this.tick(), 1000);
        }
    }

    disconnect() {
        clearInterval(this.interval);
    }

    tick() {
        this.remaining -= 1;
        if (this.remaining <= 0) {
            clearInterval(this.interval);
            this.ready();
            return;
        }
        this.render();
    }

    render() {
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = `Mail envoyé · renvoyer dans ${this.format(this.remaining)}`;
        }
    }

    // Passe le bouton en état « renvoyable ».
    ready() {
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = false;
        }
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = "Renvoyer l'email";
        }
    }

    format(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s.toString().padStart(2, '0')}`;
    }
}
