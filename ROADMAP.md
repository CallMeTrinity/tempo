# Roadmap — Application de Gestion de Fiche Horaire (Symfony)

---

## Stack technique

- PHP 8.4 + Symfony 8.0 (dernière stable : 8.0.10)
- MariaDB (Docker)
- Doctrine ORM
- Twig + Tailwind CSS (via AssetMapper)
- Symfony Security (natif, pas de bundle tiers)
- HWIOAuthBundle (Google OAuth) — vérifier compatibilité 8.0 avant install
- Docker Compose

---

## Phase 1 — Infrastructure & Setup

### 1.1 Docker
- [ ] `docker-compose.yml` : services `app` (PHP-FPM), `nginx`, `mariadb`
- [ ] `docker/nginx/default.conf`
- [ ] `docker/php/Dockerfile`
- [ ] `.env` avec `DATABASE_URL` pointant vers MariaDB
- [ ] `.env.local` (ignoré par git) pour les secrets

### 1.2 Projet Symfony
- [ ] `composer create-project symfony/skeleton:"8.0.*"`
- [ ] Installer les bundles : `orm`, `twig`, `security`, `form`, `validator`, `mailer`
- [ ] `composer require symfony/asset-mapper` (AssetMapper recommandé en Symfony 8, Webpack Encore déprécié)
- [ ] Configurer `config/packages/doctrine.yaml (Symfony 8 supporte aussi la config PHP — tableaux natifs avec autocomplétion, à préférer si l'équipe est à l'aise)`
- [ ] Vérifier la connexion DB : `php bin/console doctrine:schema:validate`

---

## Phase 2 — Modélisation & Entités

### Entités à créer

#### `User`
- `id`, `email`, `password` (hashé), `roles` (array), `firstName`, `lastName`
- `googleId` (nullable, pour OAuth)
- `createdAt`, `updatedAt`
- Relations : `OneToMany → TimeEntry`

#### `TimeEntry`
- `id`
- `user` (ManyToOne → User)
- `date` (DateType)
- `startTime` (TimeType)
- `endTime` (TimeType)
- `breakDuration` (integer, minutes)
- `note` (text, nullable)
- `status` (enum : `draft`, `submitted`, `approved`)
- `createdAt`, `updatedAt`

#### `Project` (optionnel mais utile)
- `id`, `name`, `description`, `color`
- `isActive` (bool)
- Relation : `ManyToMany → User` (assignation), `OneToMany → TimeEntry`

### Fichiers
- [ ] `src/Entity/User.php`
- [ ] `src/Entity/TimeEntry.php`
- [ ] `src/Entity/Project.php`
- [ ] Migrations : `php bin/console make:migration` puis `doctrine:migrations:migrate`

---

## Phase 3 — Authentification

### 3.1 Auth locale
- [ ] Configurer `config/packages/security.yaml` (provider, firewall, hashing)
- [ ] `src/Controller/SecurityController.php` (login, logout)
- [ ] `templates/security/login.html.twig`
- [ ] `src/Form/RegistrationFormType.php`
- [ ] `src/Controller/RegistrationController.php`
- [ ] `templates/registration/register.html.twig`

### 3.2 Google OAuth (local)
> Nécessite un projet Google Cloud avec `http://localhost` en redirect URI autorisée.

- [ ] Installer `hwi/oauth-bundle`
- [ ] `config/packages/hwi_oauth.yaml`
- [ ] Ajouter `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` dans `.env.local`
- [ ] `src/Security/GoogleAuthenticator.php` (ou utiliser le UserProvider HWI)
- [ ] Bouton "Se connecter avec Google" dans le template login

---

## Phase 4 — Gestion des fiches horaires (CRUD)

### 4.1 Saisie
- [ ] `src/Form/TimeEntryType.php` (champs : date, startTime, endTime, breakDuration, note, project)
- [ ] `src/Controller/TimeEntryController.php`
    - `GET/POST /entries/new` — création
    - `GET/POST /entries/{id}/edit` — édition
    - `DELETE /entries/{id}` — suppression
    - `GET /entries` — liste de l'utilisateur connecté
- [ ] Templates :
    - `templates/time_entry/index.html.twig`
    - `templates/time_entry/new.html.twig`
    - `templates/time_entry/edit.html.twig`

### 4.2 Calculs automatiques
- [ ] `src/Service/TimeCalculatorService.php`
    - Durée nette = (endTime - startTime) - breakDuration
    - Heures supplémentaires (si > 8h/jour)
    - Total semaine/mois

### 4.3 Soumission & validation
- [ ] Workflow simple : `draft → submitted → approved`
- [ ] Action `submit` dans le controller (change le status)
- [ ] Rôle `ROLE_MANAGER` peut approuver les fiches

---

## Phase 5 — Dashboard & Statistiques

### 5.1 Vues utilisateur
- [ ] `src/Controller/DashboardController.php`
- [ ] `templates/dashboard/index.html.twig`
- Contenu :
    - Résumé semaine en cours (heures travaillées, pauses, solde)
    - Calendrier mensuel avec statut des jours
    - Dernières entrées

### 5.2 Statistiques
- [ ] `src/Repository/TimeEntryRepository.php` avec méthodes :
    - `findByUserAndPeriod(User $user, \DateTimeInterface $start, \DateTimeInterface $end)`
    - `getWeeklyStats(User $user, int $week, int $year)`
    - `getMonthlyStats(User $user, int $month, int $year)`
- [ ] Affichage : tableau récapitulatif semaine/mois, graphique simple (Chart.js)

### 5.3 Export
- [ ] `src/Service/ExportService.php`
    - Export CSV (fiche mensuelle)
    - Export PDF via `dompdf/dompdf` ou `knplabs/knp-snappy`
- [ ] Route `GET /entries/export?month=...&format=csv|pdf`

---

## Phase 6 — Espace Manager/Admin

### 6.1 Vue d'ensemble équipe
- [ ] `src/Controller/Admin/AdminController.php` (guard `ROLE_MANAGER`)
- [ ] Liste de tous les utilisateurs avec leur solde d'heures du mois
- [ ] Accès aux fiches de chaque utilisateur

### 6.2 Approbation des fiches
- [ ] Interface liste des fiches `submitted`
- [ ] Actions `approve` / `reject` (avec commentaire optionnel)

### 6.3 Gestion des utilisateurs (optionnel)
- [ ] EasyAdminBundle ou CRUD manuel pour gérer les comptes

---

## Phase 7 — UX / Interface

- [ ] Layout de base `templates/base.html.twig` (navbar, sidebar, flash messages)
- [ ] Intégration Tailwind CSS via AssetMapper ou Webpack Encore
- [ ] `assets/app.js`, `assets/styles/app.css`
- [ ] Composant calendrier (JavaScript vanilla ou AlpineJS)
- [ ] Graphiques Chart.js pour les stats
- [ ] Responsive mobile

---

## Phase 8 — Qualité & Sécurité

- [ ] Voters Symfony : `src/Security/Voter/TimeEntryVoter.php` (un user ne peut voir/modifier que ses propres entrées)
- [ ] Validation Symfony sur toutes les entités (Assert\NotBlank, Assert\Time, etc.)
- [ ] Protection CSRF sur tous les formulaires
- [ ] Rate limiting sur login (`config/packages/rate_limiter.yaml`)
- [ ] Tests : `src/Tests/` (PHPUnit + Symfony WebTestCase)
    - Test unitaire `TimeCalculatorService`
    - Test fonctionnel login, création de fiche

---

## Structure de fichiers finale (résumé)

```
project/
├── docker-compose.yml
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── security.yaml
│   │   ├── hwi_oauth.yaml
│   │   └── rate_limiter.yaml
│   └── routes.yaml
├── src/
│   ├── Controller/
│   │   ├── SecurityController.php
│   │   ├── RegistrationController.php
│   │   ├── DashboardController.php
│   │   ├── TimeEntryController.php
│   │   └── Admin/AdminController.php
│   ├── Entity/
│   │   ├── User.php
│   │   ├── TimeEntry.php
│   │   └── Project.php
│   ├── Form/
│   │   ├── RegistrationFormType.php
│   │   └── TimeEntryType.php
│   ├── Repository/
│   │   ├── UserRepository.php
│   │   └── TimeEntryRepository.php
│   ├── Security/
│   │   ├── GoogleAuthenticator.php
│   │   └── Voter/TimeEntryVoter.php
│   └── Service/
│       ├── TimeCalculatorService.php
│       └── ExportService.php
├── templates/
│   ├── base.html.twig
│   ├── security/login.html.twig
│   ├── registration/register.html.twig
│   ├── dashboard/index.html.twig
│   └── time_entry/
│       ├── index.html.twig
│       ├── new.html.twig
│       └── edit.html.twig
├── assets/
│   ├── app.js
│   └── styles/app.css
└── migrations/
```

---

## Ordre de développement conseillé

1. Docker + Symfony skeleton fonctionnel
2. Entités + migrations
3. Auth locale (register/login)
4. CRUD TimeEntry (sans styles)
5. TimeCalculatorService + stats basiques
6. Dashboard
7. Google OAuth
8. Export CSV/PDF
9. Espace Manager
10. UI/UX + polish
11. Tests + sécurité
```
