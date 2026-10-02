# Feuille de route

## Règles métier

Elles viennent de la loi du 6 juillet 1989 sur les baux d'habitation. Chaque règle doit être couverte par des tests.

| Règle | Traduction dans l'application |
|---|---|
| Une quittance n'est délivrée que si le loyer est payé en entier. Un paiement partiel donne un reçu. | Une échéance passe par des états : à payer → partiellement payée → payée, ou en retard. |
| Révision annuelle selon l'IRL : nouveau loyer = loyer × nouvel IRL / ancien IRL. Pas de rétroactivité : le propriétaire a un an pour la demander. | Calcul isolé et testé, arrondis et dates limites compris. |
| Révision interdite pour les logements classés F ou G au DPE (en métropole depuis août 2022). | Règle qui bloque l'action, avec un message clair. |
| Dépôt de garantie : 1 mois de loyer hors charges maximum en location vide, 2 en meublé. Restitution sous 1 mois (état des lieux conforme) ou 2 mois, avec 10 % du loyer de pénalité par mois de retard. | Validation à la création du bail, calcul de la pénalité. |
| Charges : provisions mensuelles, puis régularisation annuelle selon les dépenses réelles. | Décompte annuel avec un solde à payer ou à rembourser. |
| Entrée ou sortie en cours de mois. | Premier et dernier loyers calculés au prorata. |
| Attestation d'assurance habitation à fournir chaque année par le locataire. | Relance automatique à l'expiration. |

## Les deux espaces

- **Propriétaire** : tableau de bord (loyers encaissés, impayés), biens, baux, échéances, paiements, révisions, documents.
- **Locataire** : ses échéances, ses quittances, le dépôt de son attestation d'assurance, son bail et son état des lieux.

## Modèle de données (première version)

```
User ──< Property (adresse, surface, meublé, classe DPE)
            └──< Lease (loyer, provision de charges, dépôt, trimestre IRL de référence, statut)
                   ├──< Tenant (plusieurs en cas de colocation)
                   ├──< RentDue (échéance mensuelle, unique par bail et par mois)
                   │      ├──< Payment
                   │      └──  Receipt (quittance ou reçu, en PDF)
                   ├──< RentRevision (ancien loyer, nouveau loyer, IRL utilisés)
                   └──< Document (bail, état des lieux, assurance, date d'expiration)
IrlIndex (trimestre, valeur, date de publication)
```

## Étapes

### Socle
- [x] Docker : Nginx, PHP-FPM 8.4, PostgreSQL, Mailpit
- [x] Symfony 7.4, Twig, Stimulus/Turbo, SCSS
- [x] PHPStan niveau 8, PHP-CS-Fixer, PHPUnit, CI GitHub Actions

### Version 1
- [x] Connexion, rôles propriétaire et locataire, espaces séparés, comptes de démo
- [x] Biens, avec un Voter : chaque propriétaire n'accède qu'à ses propres biens
- [x] Baux et locataires (loyer, charges, dépôt de garantie, trimestre IRL de référence, colocation)
- [ ] Fin de bail et changement de locataire (un logement F ou G ne peut pas être reloué plus cher)
- [x] Génération mensuelle des échéances, sans doublon même si la tâche tourne deux fois
- [x] Saisie des paiements, quittance ou reçu en PDF
- [ ] Espace locataire : échéances, quittances, documents, dépôt de l'attestation d'assurance
- [ ] Révision annuelle selon l'IRL, bloquée pour les classes F et G
- [ ] Démo en ligne avec comptes de test

### Version 2
- [ ] Régularisation annuelle des charges
- [ ] Dépôt de garantie et pénalités de restitution
- [ ] Import Excel des biens et baux existants (aperçu, validation ligne par ligne, rapport d'erreurs)
- [ ] Relances automatiques (impayés, assurance expirée)
- [ ] Tableau de bord avec graphiques
- [ ] Lecture d'un bail PDF par IA pour préremplir la fiche
- [ ] Jeu de données volumineux (5 000 baux, 300 000 échéances) et optimisation des requêtes
