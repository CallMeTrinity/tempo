import { Controller } from '@hotwired/stimulus';

/**
 * Affiche/masque le wrapper `times` (start/end/break) en fonction de la
 * checkbox « Télétravail ». TT = forfait journalier, donc pas d'horaires.
 *
 * Usage :
 *   <form data-controller="remote-toggle">
 *     <input type="checkbox" data-remote-toggle-target="checkbox"
 *            data-action="change->remote-toggle#apply">
 *     <div data-remote-toggle-target="times">…</div>
 *   </form>
 */
export default class extends Controller {
    static targets = ['checkbox', 'times'];

    connect() {
        this.apply();
    }

    apply() {
        const checked = this.hasCheckboxTarget ? this.checkboxTarget.checked : false;
        this.timesTargets.forEach((el) => {
            el.hidden = checked;
        });
    }
}
