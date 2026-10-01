# Gestion locative

> Projet en cours de construction. La feuille de route est dans [docs/ROADMAP.md](docs/ROADMAP.md).

Une application pour les particuliers qui louent un à dix logements : appels de loyer, quittances, révision annuelle du loyer et documents. Chaque locataire dispose de son propre espace.

## Stack

- PHP 8.4, Symfony 7.4 LTS, Doctrine ORM, PostgreSQL 17
- Twig, Stimulus, Turbo, SCSS (AssetMapper, sans Node.js)
- Nginx + PHP-FPM sous Docker, Mailpit pour les e-mails en local
- PHPUnit, Foundry, PHPStan (niveau 8), PHP-CS-Fixer, GitHub Actions

## Lancer le projet

Prérequis : Docker et `make`.

```bash
make start
```

- Application : http://localhost:8080
- E-mails envoyés en local (Mailpit) : http://localhost:8025

### Comptes de démonstration

`make fixtures` charge les données de démonstration (et vide la base de développement) :

| Espace | E-mail | Mot de passe |
|---|---|---|
| Propriétaire | `proprietaire@demo.test` | `demo1234` |
| Locataire | `locataire@demo.test` | `demo1234` |

### Commandes

`make help` liste toutes les commandes. Les plus utiles :

| Commande | Rôle |
|---|---|
| `make qa` | Style du code, analyse statique et tests, comme la CI |
| `make test` | Tests uniquement |
| `make fixtures` | Recharge les données de démonstration |
| `make sass-watch` | Recompile le SCSS à chaque modification |
| `make sh` | Shell dans le conteneur PHP |

## Choix techniques

- **Nginx + PHP-FPM** plutôt que le serveur intégré : c'est la configuration la plus courante en production, et le projet tourne localement comme il tournerait sur un serveur.
- **AssetMapper + Sass** plutôt que Webpack Encore : pas de Node.js ni d'étape de build JavaScript, ce qui simplifie l'installation et le déploiement.
- **Tests isolés en transaction** (DAMA DoctrineTestBundle) : chaque test est annulé à la fin, la base de test reste propre sans être recréée.
- **PHPStan niveau 8 dès le départ** : plus facile à tenir depuis le début qu'à rattraper sur un projet existant.
- **Les tests rejouent les migrations** pour construire leur base, au lieu de la générer depuis les entités : une migration oubliée fait échouer la CI.
- **Entités `final`** : avec PHP 8.4, Doctrine utilise les « lazy objects » natifs du langage et n'a plus besoin d'hériter des entités pour les charger à la demande.

### Connexion et espaces

- **Un compte peut être propriétaire et locataire à la fois** (rôles cumulables) : quelqu'un peut louer son ancien appartement tout en étant locataire ailleurs. Il passe alors d'un espace à l'autre depuis le bandeau.
- **Chaque espace est protégé par son préfixe d'URL** (`/proprietaire`, `/locataire`) dans `security.yaml` : une nouvelle page ajoutée sous ce préfixe est protégée par défaut, sans rien oublier.
- **E-mail insensible à la casse** : il est enregistré en minuscules, et l'e-mail saisi à la connexion est normalisé de la même façon.
- **Limitation des tentatives** : au-delà de 5 échecs, la connexion est bloquée 15 minutes. Un test le vérifie.
- **Mot de passe facultatif en base** : un locataire pourra être invité par son propriétaire avant d'avoir choisi son mot de passe.
