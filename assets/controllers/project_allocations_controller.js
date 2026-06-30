import { Controller } from '@hotwired/stimulus';

/**
 * Gère la liste dynamique d'affectations « projet + heures » d'une journée.
 *
 * - `add` clone le prototype Symfony (placeholder __name__ remplacé par un index
 *   monotone) et l'ajoute à la liste ; le select custom imbriqué se connecte
 *   tout seul (Stimulus observe le DOM).
 * - `remove` retire la ligne du DOM (orphanRemoval côté Doctrine fait le reste
 *   à la persistance).
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
        this.refreshEmpty();

        const trigger = row.querySelector('.ts-cselect-trigger');
        if (trigger) {
            trigger.focus();
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

    refreshEmpty() {
        if (!this.hasEmptyTarget) {
            return;
        }
        const hasRows = this.listTarget.querySelector('[data-project-allocations-target="row"]') !== null;
        this.emptyTarget.hidden = hasRows;
    }
}
