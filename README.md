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

`make help` liste toutes les commandes. Les plus utiles :

| Commande | Rôle |
|---|---|
| `make qa` | Style du code, analyse statique et tests, comme la CI |
| `make test` | Tests uniquement |
| `make sass-watch` | Recompile le SCSS à chaque modification |
| `make sh` | Shell dans le conteneur PHP |

## Choix techniques

- **Nginx + PHP-FPM** plutôt que le serveur intégré : c'est la configuration la plus courante en production, et le projet tourne localement comme il tournerait sur un serveur.
- **AssetMapper + Sass** plutôt que Webpack Encore : pas de Node.js ni d'étape de build JavaScript, ce qui simplifie l'installation et le déploiement.
- **Tests isolés en transaction** (DAMA DoctrineTestBundle) : chaque test est annulé à la fin, la base de test reste propre sans être recréée.
- **PHPStan niveau 8 dès le départ** : plus facile à tenir depuis le début qu'à rattraper sur un projet existant.
