import { Controller } from '@hotwired/stimulus';

/**
 * Select custom au-dessus d'un <select> natif, pour afficher l'icône et la
 * couleur de chaque projet dans le déclencheur et les options (impossible avec
 * un <select> natif).
 *
 * Le select natif (masqué) reste la source de vérité : on y reporte la valeur
 * choisie et on émet un `change`, donc la soumission du formulaire et la
 * validation serveur ne changent pas.
 *
 * Usage : cf. macro `alloc_row` dans templates/home/home.html.twig.
 */
export default class extends Controller {
    static targets = ['native', 'trigger', 'value', 'menu'];

    connect() {
        this.onOutside = this.onOutside.bind(this);
        document.addEventListener('click', this.onOutside);
    }

    disconnect() {
        document.removeEventListener('click', this.onOutside);
    }

    toggle(event) {
        event.preventDefault();
        this.isOpen() ? this.close() : this.open();
    }

    open() {
        this.menuTarget.hidden = false;
        this.triggerTarget.setAttribute('aria-expanded', 'true');
        const current = this.menuTarget.querySelector('.is-selected') || this.options()[0];
        if (current) {
            current.focus();
        }
    }

    close() {
        this.menuTarget.hidden = true;
        this.triggerTarget.setAttribute('aria-expanded', 'false');
    }

    isOpen() {
        return !this.menuTarget.hidden;
    }

    select(event) {
        const option = event.currentTarget;
        const value = option.dataset.value;

        this.nativeTarget.value = value;
        this.nativeTarget.dispatchEvent(new Event('change', { bubbles: true }));

        this.options().forEach((el) => {
            const selected = el === option;
            el.classList.toggle('is-selected', selected);
            el.setAttribute('aria-selected', selected ? 'true' : 'false');
        });

        // Recopie l'aperçu (pastille + nom) de l'option dans le déclencheur.
        this.valueTarget.innerHTML = option.innerHTML;
        this.close();
        this.triggerTarget.focus();
    }

    triggerKeydown(event) {
        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.open();
        }
    }

    // Navigation clavier au sein du menu ouvert.
    menuKeydown(event) {
        const options = this.options();
        const index = options.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            this.triggerTarget.focus();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            (options[index + 1] || options[0]).focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            (options[index - 1] || options[options.length - 1]).focus();
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (document.activeElement.matches('.ts-cselect-option')) {
                document.activeElement.click();
            }
        }
    }

    options() {
        return Array.from(this.menuTarget.querySelectorAll('.ts-cselect-option'));
    }

    onOutside(event) {
        if (this.isOpen() && !this.element.contains(event.target)) {
            this.close();
        }
    }
}
