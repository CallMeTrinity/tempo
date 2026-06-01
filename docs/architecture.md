# Architecture

Tempo est une **application Symfony 8 monolithique** côté serveur, rendue en Twig, avec une couche front légère (Stimulus + Turbo + Tailwind via AssetMapper). Il n'y a pas d'API REST exposée : tout passe par des contrôleurs HTML classiques.

## Vue d'ensemble

```
                        ┌────────────────────────────┐
        navigateur ─►  │   public/index.php         │
                       │   → Kernel (MicroKernel)   │
                       └─────────────┬──────────────┘
                                     │
                  ┌──────────────────┼──────────────────┐
                  ▼                  ▼                  ▼
            Firewalls          Routing           AdminRedirectListener
            (security.yaml)  (#[Route] attr)    (kernel.request, priority 4)
                  │                  │
                  ▼                  ▼
                Controllers (src/Controller)
                  │
                  ├─► Repositories (Doctrine ORM)  ◄── Entities + Enums
                  ├─► Forms (data_class entity)
                  ├─► TimesheetService (logique métier semaine/mois)
                  └─► Twig (templates/)
```

## Couches

### Contrôleurs (`src/Controller/`)

Tous étendent `AbstractController` et sont **fins** : extraction des paramètres de requête, appels au service métier, redirection ou render. Chaque contrôleur du domaine utilisateur déclare `#[IsGranted('IS_AUTHENTICATED_FULLY')]` au niveau de la classe ; le contrôleur admin déclare `#[IsGranted('ROLE_ADMIN')]`.

| Contrôleur                | Rôle                                                          |
|---------------------------|---------------------------------------------------------------|
| `HomeController`          | Saisie semaine + journée courante, suppression et unsubmit.   |
| `MonthController`         | Vue calendrier mensuelle 7×6 + stats du mois.                 |
| `PlanningController`      | Création de plages de jours (PTO/UTO/OFF/REMOTE) en bulk.     |
| `ProfileController`       | Édition du profil utilisateur + statistiques agrégées.        |
| `SecurityController`      | Login / logout (formulaire natif Symfony).                    |
| `RegistrationController`  | Inscription, hashing du mot de passe, blacklist check.        |
| `AdminController`         | Modération comptes, validation/refus, listing utilisateurs.   |

### Entités Doctrine (`src/Entity/`)

- `User` — utilisateur applicatif + profil RH + préférences de saisie.
- `TimeEntry` — une saisie par utilisateur et par date (`unique_user_date`).
- `Project` — référentiel projet (préparation, non utilisé pour l'instant).
- `BlacklistedEmail` — emails refusés à l'inscription.

Détails dans [domain-model.md](domain-model.md).

### Enums (`src/Enum/`)

Sources de vérité pour les états :

- `DayType` — `WORKED`, `REMOTE`, `PTO`, `UTO`, `OFF` (+ `getLabel()`, `isProductive()`, `requiresTimes()`).
- `Status` — `DRAFT`, `SUBMITTED`, `APPROVED`, `TO_BE_REVIEWED` (+ `isEditableByUser()`, `canBeSubmittedByUser()`, `canBeUnsubmittedByUser()`).
- `Roles` — `ROLE_USER`, `ROLE_ADMIN`.
- `ContractType` — `CDI`, `CDD`, `FREELANCE`, `INTERNSHIP`, `APPRENTICESHIP`.

### Repositories (`src/Repository/`)

Étendent `ServiceEntityRepository`. Les méthodes utiles :

- `TimeEntryRepository`
    - `findByUser(int $userId)`
    - `findOneByUserAndDate(User $user, \DateTimeInterface $date)`
    - `findByUserBetween(User $user, \DateTimeInterface $from, \DateTimeInterface $to)`
    - `findByUserForMonth(User $user, int $year, int $month)`
    - `findPendingApproval()` / `countPendingApproval()` — exclut les entrées des admins
    - `findRecentByUser(User $user, int $limit = 30)`
- `UserRepository`
    - `findAllExcept(User $user)` — utilisateurs hors compte courant (espace admin)
    - `findUnverified()` / `countUnverified()` — comptes en attente de validation
- `BlacklistedEmailRepository`
    - `existsByEmail(string $email)` (normalisé en lowercase + trim)
    - `findAllOrdered()`

### Service métier (`src/Service/TimesheetService.php`)

Centralise la **logique de présentation et de calcul** :

- `buildWeekView(User, $weekStart)` : construit les 7 cellules de la semaine, avec entrée réelle ou entrée *virtuelle* (suggestion REMOTE non persistée pour les `defaultRemoteDays`).
- `computeWeeklyStats(User, TimeEntry[])` : total heures, heures sup, déficit, jours travaillés, progrès (%) vs `weeklyHours`.
- `computeMonthlyStats(User, year, month)` : total heures, jours saisis, jours ouvrés attendus dans le mois, heures attendues, sup/déficit.
- `normalizeMonday(\DateTimeInterface)` : retourne le lundi 00:00 de la semaine de la date donnée.

Les contrôleurs `HomeController`, `MonthController` et `AdminController` consomment ce service.

### Forms (`src/Form/`)

Tous désactivent `csrf_protection` au niveau du form (les pages sont déjà derrière `IsGranted`, et le contrôleur JS CSRF stateless de Symfony ne fait pas matcher les tokens côté serveur). La protection CSRF des actions destructrices (delete, unsubmit, approve…) est faite manuellement via `isCsrfTokenValid()` dans les contrôleurs.

| Form                    | Lié à            | Particularité                                         |
|-------------------------|------------------|-------------------------------------------------------|
| `TimeEntryType`         | `TimeEntry`      | Toggle `isRemote` non mappé sync le `dayType` en POST_SUBMIT. |
| `DayPlanningType`       | tableau (non lié)| `startDate`/`endDate`/`dayType`/`note`.               |
| `UserProfileType`       | `User`           | `defaultRemoteDays` = ChoiceType Lun–Ven multi expanded.|
| `RegistrationFormType`  | `User`           | `plainPassword` + `agreeTerms` non mappés.            |

### Event listeners (`src/EventListener/`)

`AdminRedirectListener` (priorité 4, après le RouterListener et le FirewallListener) intercepte toute requête principale d'un utilisateur `ROLE_ADMIN` qui cible une route « utilisateur » (préfixes `app_home`, `app_month`, `app_profile`, `app_time_entry_`, `app_week_`, `app_planning_`) et le redirige vers `app_admin_index`.

### Sécurité (`config/packages/security.yaml`)

- **Provider** : entité `App\Entity\User`, propriété de lookup `username`. ⚠️ Le formulaire de login envoie un identifiant utilisateur — l'app sait que pendant l'inscription, `username` est dérivé de la partie locale de l'email (avant `@`). Si vous personnalisez le login, vérifier ce point.
- **Firewall** `dev` : libère `/(_profiler|_wdt|assets|build)/`.
- **Firewall** `main` : `form_login` avec CSRF activée, logout sur `/logout`.
- **Access control** : `/login` et `/register` publics, `/admin` réservé `ROLE_ADMIN`, tout le reste `ROLE_USER` ou `ROLE_ADMIN`.

### Vérification d'email (`src/Security/EmailVerifier.php`)

Wrapper autour de `symfonycasts/verify-email-bundle`. Génère une URL signée et envoie un email templated. Actuellement le flux est branché sur le concept de `User::isVerified()` mais la **validation se fait via un admin** côté `AdminController::approveAccount` (le terme `isVerified` est réutilisé pour signifier « validé par l'admin »). Le `EmailVerifier` reste disponible pour un branchement futur de la vérification par email.

## Couche front

### Asset Mapper

Symfony AssetMapper gère le pipeline d'assets sans bundler. L'`importmap.php` déclare les modules JS :

- `app` (`assets/app.js`) — entrypoint qui charge `stimulus_bootstrap.js` et `styles/app.css`
- `@hotwired/stimulus`, `@hotwired/turbo` — installés via importmap
- `@symfony/stimulus-bundle` — pointe vers le loader fourni par le bundle

### Stimulus controllers (`assets/controllers/`)

- `remote_toggle_controller.js` — masque/affiche le bloc start/end/break selon la checkbox « Télétravail » dans le formulaire de saisie.
- `csrf_protection_controller.js` — fourni par défaut (gestion CSRF côté JS).

### Tailwind CSS

Géré par `symfonycasts/tailwind-bundle`. Le CSS source est dans `assets/styles/app.css`, compilé vers le même fichier consommé par AssetMapper. En dev, `symfony server:start` lance `tailwind:build --watch` via `.symfony.local.yaml`.

### Turbo

`@hotwired/turbo` est chargé dans l'importmap mais l'app reste très majoritairement *full reload* — Turbo accélère la navigation sans qu'il y ait de partials Turbo Frames spécifiques pour l'instant.

## Templates Twig (`templates/`)

```
templates/
├── base.html.twig           # layout minimal (head + importmap)
├── components/              # partials réutilisables
│   ├── auth_panel.html.twig
│   ├── flashes.html.twig
│   ├── planning_form.html.twig
│   ├── topbar.html.twig
│   └── week_nav.html.twig
├── home/home.html.twig
├── month/month.html.twig
├── profile/profile.html.twig
├── admin/
│   ├── _subnav.html.twig
│   ├── entries.html.twig
│   ├── registrations.html.twig
│   ├── user_detail.html.twig
│   └── users.html.twig
├── registration/
│   ├── confirmation_email.html.twig
│   └── register.html.twig
└── security/login.html.twig
```

## Base de données

- **MariaDB 11.4** via Docker (en local). Le DSN inclut `serverVersion=11.4.4-MariaDB`.
- **Naming strategy** : `underscore` (`workingDaysPerWeek` côté ORM ↔ `working_days_per_week` en SQL).
- **Mappings** : par attribut PHP (`#[ORM\Entity]` etc.) — pas de XML/YAML.
- **Migrations** : versionnées dans `migrations/`, gérées par `doctrine/doctrine-migrations-bundle`. Pour générer une migration depuis le diff : `php bin/console make:migration`.
- **En `prod`** : caches Doctrine activés (`query_cache_driver` et `result_cache_driver` mappés sur `cache.app` / `cache.system`).

## Tests

- Bootstrap dans `tests/bootstrap.php` (charge `.env`).
- Config PHPUnit 13 dans `phpunit.dist.xml` avec mode strict (`failOnDeprecation`, `failOnNotice`, `failOnWarning`).
- En `test`, Doctrine applique un suffixe `_test%env(default::TEST_TOKEN)%` au nom de base et les password hashers sont en cost minimum.
- Pour l'instant `tests/` contient le bootstrap seul — pas de tests fonctionnels.
