# Tempo

Application de gestion de fiches horaires (timesheets) pour suivre et déclarer son temps de travail au quotidien.

## Présentation

Tempo permet à chaque collaborateur de saisir, semaine après semaine, le détail de ses journées de travail : heures effectuées au bureau, télétravail, congés payés ou non, jours d'absence. L'application calcule automatiquement les heures travaillées, les heures supplémentaires et le suivi mensuel par rapport au contrat de l'utilisateur.

Un espace administrateur permet par ailleurs de superviser les fiches de l'équipe, de valider en bulk les semaines soumises, et de modérer les inscriptions (validation manuelle de comptes, liste noire d'emails).

## Fonctionnalités principales

- **Saisie hebdomadaire** : navigation semaine par semaine avec une vue jour par jour.
- **Types de journée** : Bureau (`WORKED`), Télétravail (`REMOTE`), Congé payé (`PTO`), Congé non payé (`UTO`), Absent (`OFF`).
- **Calcul automatique** des heures travaillées (`end − start − pause`), avec forfait journalier pour télétravail et congés selon le contrat.
- **Vue mensuelle** sous forme de calendrier avec récapitulatif (heures du mois, heures supplémentaires, jours saisis/attendus).
- **Planification de plages** : poser des PTO/UTO/OFF ou pré-réserver du télétravail sur une plage de dates.
- **Profil utilisateur** : type de contrat, heures hebdomadaires, date de début de contrat, jours travaillés par semaine, jours de télétravail par défaut, pause par défaut.
- **Pré-suggestion** des jours de télétravail récurrents pour accélérer la saisie.
- **Workflow de validation** : brouillon → soumis → approuvé / à revoir. Soumission en bloc par semaine.
- **Authentification** : inscription locale, connexion par mot de passe, support Google ID (champ prévu sur le `User`).
- **Espace admin** : vue d'ensemble des utilisateurs et de leurs fiches, validation/renvoi en bulk par semaine, validation manuelle des inscriptions, blacklist d'emails.

## Stack technique

- **PHP 8.4** + **Symfony 8.0**
- **Doctrine ORM 3** + **MariaDB** (via Docker Compose)
- **Twig** + **Tailwind CSS** (via `symfonycasts/tailwind-bundle` et AssetMapper)
- **Symfony UX** (Stimulus, Turbo)
- **Symfony Security** natif + `symfonycasts/verify-email-bundle`
- **PHPUnit 13** pour les tests

## Arborescence du projet

```
tempo/
├── assets/             # JS/CSS front (Stimulus, Tailwind, importmap)
├── bin/                # console Symfony et phpunit
├── config/             # configuration Symfony (packages, routes, security)
├── docs/               # documentation détaillée (voir docs/README.md)
├── migrations/         # migrations Doctrine
├── public/             # document root (index.php)
├── src/
│   ├── Controller/     # Home, Month, Planning, Profile, Admin, Security, Registration
│   ├── Entity/         # User, TimeEntry, Project, BlacklistedEmail
│   ├── Enum/           # DayType, ContractType, Status, Roles
│   ├── EventListener/  # AdminRedirectListener
│   ├── Form/           # TimeEntryType, UserProfileType, DayPlanningType, RegistrationFormType
│   ├── Repository/     # repositories Doctrine
│   ├── Security/       # EmailVerifier
│   └── Service/        # TimesheetService (logique métier semaine/mois)
├── templates/          # vues Twig
├── tests/              # tests PHPUnit
├── translations/       # fichiers de traduction
├── compose.yaml        # services Docker (MariaDB)
└── compose.override.yaml # services dev (ports, Mailpit)
```

## Prérequis

- PHP ≥ 8.4 avec les extensions `ctype` et `iconv`
- Composer
- Docker / Docker Compose (pour la base de données et Mailpit)
- Symfony CLI (recommandé pour servir l'app en local et lancer les workers)

## Installation

```bash
# 1. Cloner le dépôt
git clone <repo-url> tempo && cd tempo

# 2. Installer les dépendances PHP (lance aussi cache:clear, assets:install, importmap:install)
composer install

# 3. Démarrer les services Docker (MariaDB + Mailpit)
docker compose up -d

# 4. Créer le schéma de base de données
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Démarrer le serveur de dev
#    (utilise .symfony.local.yaml pour lancer aussi le worker tailwind --watch)
symfony server:start
```

L'application est ensuite disponible sur `https://localhost:8000` (ou le port indiqué par Symfony CLI).

Pour Mailpit (capture des emails sortants en dev) : `http://localhost:8025`.

### Sans Symfony CLI

```bash
# Construit le CSS Tailwind une fois
php bin/console tailwind:build

# Démarre un serveur PHP intégré
php -S 127.0.0.1:8000 -t public/
```

## Configuration

La configuration passe par les fichiers `.env`. Les fichiers chargés successivement (le plus prioritaire en dernier) :

- `.env` — valeurs par défaut versionnées
- `.env.local` — overrides locaux non versionnés (à créer au besoin)
- `.env.$APP_ENV` — défauts par environnement (ex : `.env.prod`)
- `.env.$APP_ENV.local` — overrides par environnement

### Variables d'environnement

| Variable                  | Obligatoire | Défaut                                              | Description                                                       |
|---------------------------|-------------|-----------------------------------------------------|-------------------------------------------------------------------|
| `APP_ENV`                 | oui         | `dev`                                               | Environnement Symfony (`dev`, `test`, `prod`).                    |
| `APP_DEBUG`               | non         | activé en `dev`                                     | Mode debug Symfony.                                               |
| `APP_SECRET`              | oui         | (vide en local)                                     | Secret applicatif (CSRF, signatures). À définir en prod.          |
| `APP_SHARE_DIR`           | non         | `var/share`                                         | Répertoire de partage applicatif.                                 |
| `DATABASE_URL`            | oui         | `mysql://db:db@127.0.0.1:3306/db?serverVersion=...` | DSN de la base de données (MariaDB par défaut).                   |
| `MESSENGER_TRANSPORT_DSN` | non         | `doctrine://default?auto_setup=0`                   | Transport Symfony Messenger.                                      |
| `MAILER_DSN`              | non         | `null://null`                                       | DSN du mailer (mettre `smtp://localhost:1025` pour Mailpit local).|
| `DEFAULT_URI`             | non         | `http://localhost`                                  | URI utilisée en CLI pour générer des URLs absolues.               |

Le fichier `.env.prod` (non versionné, ignoré par Git) est utilisé pour la production : il doit contenir un `APP_SECRET` et un `DATABASE_URL` propres à l'environnement cible. Voir [`docs/deployment.md`](docs/deployment.md).

## Commandes utiles

```bash
# Symfony console
php bin/console list
php bin/console debug:router          # liste des routes
php bin/console debug:container       # services
php bin/console doctrine:migrations:migrate
php bin/console make:migration        # nouvelle migration depuis le diff

# Tailwind (regénère le CSS)
php bin/console tailwind:build
php bin/console tailwind:build --watch

# Asset Mapper
php bin/console asset-map:compile     # build prod
php bin/console importmap:install
php bin/console debug:asset-map

# Tests
php bin/phpunit
```

Le worker `tailwind:build --watch` est démarré automatiquement par `symfony server:start` via `.symfony.local.yaml`.

## Tests

```bash
# Tous les tests
php bin/phpunit

# Un seul fichier
php bin/phpunit tests/SomeTest.php
```

PHPUnit est configuré avec `failOnDeprecation`, `failOnNotice` et `failOnWarning` : tout warning ou notice fait échouer la suite.

## Documentation

Pour aller plus loin, voir [`docs/`](docs/README.md) :

- [Getting started](docs/getting-started.md) — installation pas-à-pas et premier login
- [Architecture](docs/architecture.md) — vue d'ensemble du code
- [Modèle de données](docs/domain-model.md) — entités, enums, règles
- [Configuration](docs/configuration.md) — variables d'environnement et paramétrage
- [Routes & contrôleurs](docs/routes.md) — référence des routes HTTP
- [Workflow d'une fiche](docs/workflows.md) — DRAFT → SUBMITTED → APPROVED, planification
- [Guide administrateur](docs/admin-guide.md) — modération des comptes, validation des semaines
- [Développement](docs/development.md) — bonnes pratiques, ajout de routes/entités
- [Déploiement](docs/deployment.md) — build prod, variables, base de données

## Contribuer

1. Créer une branche depuis `main`.
2. Suivre les conventions du `.editorconfig` (4 espaces, LF, UTF-8) et le style Symfony des contrôleurs/entités existants.
3. Ajouter ou mettre à jour les migrations Doctrine si le schéma change (`make:migration`).
4. Lancer `php bin/phpunit` avant de pousser.
5. Ouvrir une merge request sur GitLab.

## Licence

Code source propriétaire (`composer.json` → `"license": "proprietary"`). Tous droits réservés.
