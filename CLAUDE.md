# Gestion locative

Application Symfony 7.4 de gestion locative (espaces propriétaire et locataire). Le contexte métier et les étapes sont dans `docs/ROADMAP.md`.

## Commandes

Tout tourne dans Docker, via `make` :

- `make start` : démarre l'application (http://localhost:8080)
- `make qa` : style, PHPStan et tests. À lancer avant chaque commit.
- `make sh` : shell dans le conteneur PHP (`bin/console`, `composer`…)
- `make fixtures` : données de démo (Foundry, `src/Story/AppStory.php`)
- `make logs` : logs du worker, qui exécute les tâches planifiées (`src/Schedule.php`) et les messages asynchrones

Les tests utilisent les factories Foundry (`src/Factory/`). La base de test est reconstruite automatiquement en rejouant les migrations.

## Conventions

- Code et noms en anglais, textes de l'interface en français.
- `declare(strict_types=1)` partout, classes `final` par défaut, entités comprises (lazy objects natifs de PHP 8.4).
- Montants en centimes (`int`), jamais en `float`. Saisie avec `EuroAmountType`, affichage avec le filtre Twig `money`.
- Dates affichées avec les filtres Twig `long_date` (« 1er juillet 2024 ») et `month_year` (« octobre 2026 »).
- Toute règle qui dépend de la date du jour l'obtient via `ClockInterface` (et non `new \DateTimeImmutable()`), pour que les tests puissent la figer avec `ClockSensitiveTrait::mockTime()`.
- Dates en `DateTimeImmutable`.
- L'état d'une échéance est géré par le workflow `rent_due` : un paiement s'enregistre toujours via `PaymentRecorder` (verrou, montant encaissé, transition), jamais en modifiant l'entité directement.
- Règles métier dans des services dédiés, testés unitairement, sans dépendance au contrôleur.
- Droits d'accès via des Voters, avec un test fonctionnel qui vérifie qu'un utilisateur n'accède pas aux données d'un autre.
- Les formulaires travaillent sur un objet intermédiaire (`src/Form/Data/`), jamais directement sur l'entité.
- Chaque fonctionnalité arrive avec ses tests et sa migration Doctrine.
