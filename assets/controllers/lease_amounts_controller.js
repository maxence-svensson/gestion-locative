import { Controller } from '@hotwired/stimulus';

/*
 * Pendant la saisie d'un bail, affiche le total appelé chaque mois et le dépôt de garantie maximum.
 * Simple aide à la saisie : la vraie vérification est faite côté serveur (LeaseData).
 */
export default class extends Controller {
    static targets = ['rent', 'charges', 'total', 'depositMax'];
    static values = { furnished: Boolean };

    #formatter = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

    connect() {
        this.update();
    }

    update() {
        const rent = this.#parse(this.rentTarget.value);
        const charges = this.#parse(this.chargesTarget.value);

        this.totalTarget.textContent = rent === null ? '—' : this.#formatter.format(rent + (charges ?? 0));
        this.depositMaxTarget.textContent = rent === null ? '—' : this.#formatter.format(rent * (this.furnishedValue ? 2 : 1));
    }

    // « 1 234,56 » → 1234.56 ; null si la saisie n'est pas un montant
    #parse(value) {
        const normalized = value.replace(/[\s  ]/g, '').replace(',', '.');
        if (normalized === '') {
            return null;
        }

        const amount = Number(normalized);

        return Number.isFinite(amount) ? amount : null;
    }
}
