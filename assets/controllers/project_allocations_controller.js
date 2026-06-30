import { Controller } from '@hotwired/stimulus';

/**
 * Gère la liste dynamique d'affectations « projet + heures » d'une journée.
 *
 * - `add` clone le prototype Symfony (placeholder __name__ remplacé par un index
 *   monotone) et l'ajoute à la liste.
 * - `remove` retire la ligne du DOM (orphanRemoval côté Doctrine fait le reste
 *   à la persistance).
 * - `syncPreview` reflète la couleur du projet sélectionné sur la pastille.
 *
 * Usage :
 *   <div data-controller="project-allocations"
 *        data-project-allocations-prototype-value="<…markup avec __name__…>"
 *        data-project-allocations-index-value="2">
 *     <div data-project-allocations-target="list">…</div>
 *     <button data-action="project-allocations#add">…</button>
 *   </div>
 */
export default class extends Controller {
    static targets = ['list', 'empty'];
    static values = { prototype: String, index: Number };

    connect() {
        this.listTarget
            .querySelectorAll('[data-project-allocations-target="row"]')
            .forEach((row) => this.syncRowPreview(row));
        this.refreshEmpty();
    }

    add(event) {
        event.preventDefault();
        const markup = this.prototypeValue.replace(/__name__/g, this.indexValue);
        this.indexValue += 1;

        const template = document.createElement('template');
        template.innerHTML = markup.trim();
        const row = template.content.firstElementChild;
        this.listTarget.appendChild(row);
        this.syncRowPreview(row);
        this.refreshEmpty();

        const select = row.querySelector('select');
        if (select) {
            select.focus();
        }
    }

    remove(event) {
        event.preventDefault();
        const row = event.target.closest('[data-project-allocations-target="row"]');
        if (row) {
            row.remove();
            this.refreshEmpty();
        }
    }

    syncPreview(event) {
        const row = event.target.closest('[data-project-allocations-target="row"]');
        if (row) {
            this.syncRowPreview(row);
        }
    }

    syncRowPreview(row) {
        const select = row.querySelector('select');
        const dot = row.querySelector('[data-project-allocations-target="dot"]');
        if (!select || !dot) {
            return;
        }
        const option = select.options[select.selectedIndex];
        const hex = option ? option.getAttribute('data-hex') : null;
        dot.style.setProperty('--pc', hex || 'transparent');
    }

    refreshEmpty() {
        if (!this.hasEmptyTarget) {
            return;
        }
        const hasRows = this.listTarget.querySelector('[data-project-allocations-target="row"]') !== null;
        this.emptyTarget.hidden = hasRows;
    }
}
