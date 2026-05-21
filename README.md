# Tempo

Application de gestion de fiches horaires (timesheets) pour suivre et déclarer son temps de travail au quotidien.

## Présentation

Tempo permet à chaque collaborateur de saisir, semaine après semaine, le détail de ses journées de travail : heures effectuées au bureau, télétravail, congés payés ou non, jours d'absence. L'application calcule automatiquement les heures travaillées, les heures supplémentaires et le suivi mensuel par rapport au contrat de l'utilisateur.

Un espace administrateur permet par ailleurs de superviser les fiches de l'équipe et de valider/corriger les saisies en bulk.

## Fonctionnalités principales

- **Saisie hebdomadaire** : navigation semaine par semaine avec une vue jour par jour.
- **Types de journée** : Bureau (`WORKED`), Télétravail (`REMOTE`), Congé payé (`PTO`), Congé non payé (`UTO`), Absent (`OFF`).
- **Calcul automatique** des heures travaillées (start/end − pause), avec forfait journalier pour télétravail et congés selon le contrat.
- **Vue mensuelle** sous forme de calendrier avec récapitulatif (heures du mois, heures supplémentaires, jours saisis/attendus).
- **Profil utilisateur** : type de contrat, heures hebdomadaires, date de début de contrat, jours travaillés par semaine, jours de télétravail par défaut, pause par défaut.
- **Pré-suggestion** des jours de télétravail récurrents pour accélérer la saisie.
- **Authentification** : inscription locale avec vérification d'email, connexion par mot de passe, support Google ID (champ prévu sur le `User`).
- **Espace admin** : vue d'ensemble des utilisateurs et de leurs fiches, validation bulk (heures, comptes), gestion des emails blacklistés.

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
├── assets/             # JS/CSS front (Stimulus, Tailwind)
├── bin/                # console Symfony
├── config/             # configuration Symfony (packages, routes, security)
├── migrations/         # migrations Doctrine
├── public/             # document root
├── src/
│   ├── Controller/     # Home, Month, Planning, Profile, Admin, Security, Registration
│   ├── Entity/         # User, TimeEntry, Project, BlacklistedEmail
│   ├── Enum/           # DayType, ContractType, Status, Roles
│   ├── EventListener/  # listeners Doctrine (timestamps, etc.)
│   ├── Form/           # TimeEntryType, UserProfileType, DayPlanningType, RegistrationFormType
│   ├── Repository/     # repositories Doctrine
│   ├── Security/       # EmailVerifier
│   └── Service/        # TimesheetService (logique métier semaine/mois)
├── templates/          # vues Twig
├── tests/              # tests PHPUnit
├── translations/       # fichiers de traduction
├── compose.yaml        # services Docker (MariaDB)
└── ROADMAP.md          # feuille de route détaillée par phases
```

## Installation

### Prérequis

- PHP ≥ 8.4 avec les extensions `ctype` et `iconv`
- Composer
- Docker / Docker Compose (pour la base de données)
- Symfony CLI (recommandé pour servir l'app en local)

### Mise en place

```bash
# 1. Cloner le dépôt
git clone <repo-url> tempo && cd tempo

# 2. Installer les dépendances PHP
composer install

# 3. Lancer le serveur de dev (inclut composer et tailwind)
symfony server:start

# 4. Créer le schéma
php bin/console doctrine:migrations:migrate
# ou : php -S 127.0.0.1:8000 -t public/
```

L'application est ensuite disponible sur `http://localhost:8000`.

### Configuration

La chaîne de connexion par défaut pointe sur le conteneur MariaDB défini dans `compose.yaml` :

```
DATABASE_URL="mysql://db:db@127.0.0.1:3306/db?serverVersion=11.4.4-MariaDB&charset=utf8mb4"
```

Les variables sensibles (mailer, secret, etc.) peuvent être surchargées dans un fichier `.env.local` non versionné.

## Modèle de données (résumé)

- **User** : compte utilisateur (email, mot de passe, rôle), profil RH (nom, prénom, intitulé, type de contrat, heures hebdo, date de début), préférences de saisie (`workingDaysPerWeek`, `defaultRemoteDays`, `defaultBreakMinutes`).
- **TimeEntry** : une saisie par utilisateur et par date (`unique_user_date`). Porte le `DayType`, et selon le type les horaires `startTime`/`endTime`/`breakDuration` (uniquement pour `WORKED`). Calcule `getHoursWorked()` automatiquement.
- **Project** : référentiel projet (préparation pour une future imputation par projet).
- **BlacklistedEmail** : emails interdits à l'inscription.

## Logique métier clé

- `TimesheetService` centralise la construction des vues semaine/mois et le calcul des statistiques (heures faites, heures sup, déficit, jours saisis vs attendus).
- `User::getExpectedDailyHours()` = `weeklyHours / workingDaysPerWeek` — sert de forfait journalier pour les jours non chronométrés.
- `TimeEntry::validateConsistency()` impose `startTime`/`endTime` pour un jour `WORKED` et vérifie que la fin est postérieure au début.

## Tests

```bash
php bin/phpunit
```
