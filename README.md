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
| `make logs` | Suit les logs du worker (tâches planifiées) |
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

### Biens

- **Deux protections complémentaires** : un Voter (`PropertyVoter`) contrôle l'accès à un bien précis, et la requête de la liste filtre par propriétaire. Le Voter seul ne suffirait pas à empêcher une liste d'afficher les biens des autres.
- **Des tests qui échouent si la protection disparaît** : pour chaque action (consulter, modifier, supprimer), un test vérifie qu'un autre propriétaire reçoit une erreur 403, même avec un jeton CSRF valide. J'ai vérifié qu'ils échouent bien quand on casse volontairement le Voter.
- **Formulaire lié à un objet intermédiaire** (`PropertyData`) plutôt qu'à l'entité : le formulaire accepte des champs vides pendant la saisie, alors que l'entité `Property` est toujours complète et valide.
- **Statut 422 sur un formulaire invalide** : c'est ce qu'attend Turbo pour réafficher le formulaire avec ses erreurs sans recharger la page.
- **Règle métier du DPE** : un logement classé F ou G est signalé « loyer gelé » ; la même règle bloquera la révision annuelle du loyer.

### Baux et locataires

- **Montants en centimes** (`int`), jamais en nombres à virgule : 0,1 + 0,2 ne vaut pas exactement 0,3 en virgule flottante.
- **Plafond légal du dépôt de garantie** (`DepositLimit`) : un mois de loyer hors charges en location vide, deux en meublé. Le formulaire affiche un message qui donne le montant maximum, et l'entité `Lease` refuse elle-même un dépôt trop élevé, en filet de sécurité.
- **Le bail garde le type avec lequel il a été signé** : s'il est meublé, il le reste même si la fiche du bien change ensuite.
- **Colocation** : les locataires s'ajoutent et se retirent sans recharger la page (contrôleur Stimulus `form-collection`). Chacun doit avoir sa propre adresse e-mail.
- **Aide à la saisie** : le total mensuel et le dépôt maximum se calculent en direct pendant la saisie (contrôleur Stimulus `lease-amounts`). La vraie vérification reste faite côté serveur.
- **Un bien loué ne peut pas être supprimé**, et n'a qu'un bail en cours.

### Échéances mensuelles

- **Génération idempotente** (`RentDueGenerator`) : relancée autant de fois qu'on veut, elle ne crée que les mois manquants. Elle tourne donc **chaque jour** plutôt qu'une fois par mois : si le serveur est arrêté le 1er, le mois est rattrapé le lendemain.
- **Trois protections contre les doublons** : la génération ignore les mois déjà présents, un verrou PostgreSQL (« advisory lock ») empêche deux générations simultanées, et une contrainte d'unicité en base (bail + mois) reste le dernier filet.
- **Tâche planifiée** avec Symfony Scheduler, exécutée par un worker Messenger (service `worker` de `compose.yaml`). La même génération est disponible en commande : `bin/console app:rent-dues:generate`.
- **Premier loyer au prorata** quand le bail commence en cours de mois, calculé en nombres entiers et arrondi au centime (`RentSchedule`, testé unitairement sur les mois de 28, 30 et 31 jours).
- **Suivi à partir du mois de saisie** pour un bail déjà en cours : le propriétaire n'a pas à marquer comme payés des mois antérieurs à son arrivée dans l'application.
- **Le temps est injecté** (`ClockInterface`) : les tests figent la date du jour, et ne dépendent donc pas du jour où ils tournent.

## Problèmes rencontrés

- **« 1 234,56 » refusé dans un champ montant.** Le champ `MoneyType` de Symfony rejette par défaut un montant écrit avec un espace entre les milliers, la façon habituelle d'écrire en français. Il faut activer l'option `grouping`. J'en ai fait un champ réutilisable (`EuroAmountType`), avec un test pour chaque façon d'écrire un montant (espace, espace insécable, virgule, point).
- **Un test de performance qui se trompait.** Le test qui compte les requêtes SQL de la liste des biens trouvait 10 requêtes au lieu de 3 : il comptait aussi les insertions faites par le test juste avant, le noyau n'étant redémarré qu'à la requête suivante. Le test fait maintenant une requête « à blanc » avant de mesurer.
- **Ajouter une colonne obligatoire à une table déjà remplie.** La migration générée (`ADD ... NOT NULL`) aurait échoué sur une base contenant des baux. Elle ajoute maintenant la colonne vide, la remplit, puis la rend obligatoire.
- **Des tests qui passaient pour une mauvaise raison.** Pour chaque garde-fou (Voter, nombre de requêtes, saisie des montants), j'ai introduit volontairement le défaut qu'il surveille, pour vérifier qu'il échoue bien.
- **Le test du blocage après 5 échecs de connexion échouait** : en test, le cache en mémoire est vidé entre deux requêtes, donc le compteur repartait de zéro. Solution : cache sur disque, remis à zéro au début de chaque test.
