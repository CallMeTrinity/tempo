import { Controller } from '@hotwired/stimulus';

/*
 * Pile de toasts dans le coin de l'écran.
 * - conteneur en position: fixed → n'affecte jamais le flux du DOM
 * - 3 toasts visibles au maximum, le surplus attend en file
 * - chaque toast disparaît au bout de 5s ou au clic
 */
export default class extends Controller {
    static targets = ['toast'];
    static values = {
        max: { type: Number, default: 3 },
        duration: { type: Number, default: 5000 },
    };

    connect() {
        this.timeouts = new Map();
        this.queue = [...this.toastTargets];
        this.visible = new Set();
        this.pump();
    }

    disconnect() {
        this.timeouts.forEach((id) => clearTimeout(id));
        this.timeouts.clear();
    }

    // Affiche autant de toasts que la limite le permet.
    pump() {
        while (this.visible.size < this.maxValue && this.queue.length > 0) {
            this.show(this.queue.shift());
        }
    }

    show(el) {
        this.visible.add(el);
        el.hidden = false;
        // Reflow avant l'ajout de la classe pour déclencher la transition d'entrée.
        requestAnimationFrame(() => el.classList.add('is-visible'));
        this.timeouts.set(el, setTimeout(() => this.dismiss(el), this.durationValue));
    }

    // Déclenché par le clic sur un toast (data-action).
    onClick(event) {
        this.dismiss(event.currentTarget);
    }

    dismiss(el) {
        if (!this.visible.has(el)) {
            return;
        }
        this.visible.delete(el);
        clearTimeout(this.timeouts.get(el));
        this.timeouts.delete(el);

        el.classList.remove('is-visible');
        el.classList.add('is-leaving');

        let removed = false;
        const remove = () => {
            if (removed) {
                return;
            }
            removed = true;
            el.remove();
            this.pump();
        };
        el.addEventListener('transitionend', remove, { once: true });
        // Filet de sécurité si transitionend ne se déclenche pas.
        setTimeout(remove, 400);
    }
}
