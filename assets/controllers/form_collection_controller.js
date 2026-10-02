import { Controller } from '@hotwired/stimulus';

/*
 * Ajoute et retire des lignes d'un CollectionType Symfony (ici, les locataires d'un bail).
 *
 * Le modèle d'une ligne (prototype) est fourni par Symfony : on remplace « __name__ »
 * par un index qui n'a encore jamais servi, pour que chaque champ ait un nom unique.
 */
export default class extends Controller {
    static targets = ['list', 'entry', 'addButton'];
    static values = {
        prototype: String,
        index: Number,
        max: Number,
    };

    connect() {
        this.#updateAddButton();
    }

    add() {
        if (this.entryTargets.length >= this.maxValue) {
            return;
        }

        const html = this.prototypeValue.replace(/__name__/g, String(this.indexValue));
        this.listTarget.insertAdjacentHTML('beforeend', html);
        this.indexValue++;

        // Place le curseur dans le premier champ de la nouvelle ligne
        this.entryTargets.at(-1)?.querySelector('input')?.focus();
        this.#updateAddButton();
    }

    remove(event) {
        event.currentTarget.closest('[data-form-collection-target="entry"]')?.remove();
        this.#updateAddButton();
    }

    #updateAddButton() {
        if (this.hasAddButtonTarget) {
            this.addButtonTarget.hidden = this.entryTargets.length >= this.maxValue;
        }
    }
}
