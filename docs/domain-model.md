# Modèle de données

Le domaine de Tempo tourne autour de trois entités centrales — `User`, `TimeEntry`, `BlacklistedEmail` — et d'une entité préparatoire (`Project`).

## Schéma synthétique

```
┌────────────────┐  1   *  ┌─────────────────┐
│      User      │─────────│   TimeEntry     │
│ (utilisateur)  │         │  (saisie/jour)  │
└────────────────┘         └─────────────────┘
        │
        │ blacklisted_by
        ▼
┌──────────────────────┐
│  BlacklistedEmail    │
└──────────────────────┘

┌────────────┐
│  Project   │  (entité préparée, non encore reliée à TimeEntry)
└────────────┘
```

## Entité `User`

`src/Entity/User.php` — implémente `UserInterface` et `PasswordAuthenticatedUserInterface`.

### Champs

| Champ                  | Type                  | Notes                                                                  |
|------------------------|-----------------------|------------------------------------------------------------------------|
| `id`                   | int (PK)              | auto-généré                                                            |
| `username`             | ?string(255)          | propriété de lookup du provider Security ; dérivée de l'email à l'inscription. |
| `email`                | string(255)           | unique côté UX (`UniqueEntity`).                                       |
| `password`             | string(255)           | hash, géré par `UserPasswordHasherInterface`.                          |
| `googleId`             | ?string(255)          | préparation pour une auth Google.                                      |
| `createdAt`            | datetime_immutable    | défaut SQL `CURRENT_TIMESTAMP`.                                        |
| `updatedAt`            | datetime_immutable    | défaut SQL `CURRENT_TIMESTAMP`, mis à jour manuellement dans les contrôleurs. |
| `role`                 | `Roles` enum          | `ROLE_USER` (défaut) ou `ROLE_ADMIN`.                                  |
| `isVerified`           | bool                  | « validé par un admin ». Sans cette validation, l'utilisateur peut quand même se connecter et utiliser l'app. |
| `firstName`, `lastName`| ?string(255)          | renseignés via le profil ; `firstName` est pré-rempli depuis l'email à l'inscription. |
| `contractType`         | `ContractType` enum   | CDI, CDD, Freelance, Stage, Alternance.                                |
| `weeklyHours`          | ?float                | ex : 35, 37.5, 39.                                                     |
| `contractStartDate`    | ?DateTime             | borne basse pour la saisie (avant : refusé avec un flash).             |
| `jobTitle`             | ?string(255)          | intitulé de poste.                                                     |
| `workingDaysPerWeek`   | int (défaut 5)        | 1–5, week-ends toujours chômés (l'UI le rappelle).                     |
| `defaultRemoteDays`    | JSON array<int>       | ISO weekdays 1..7 (l'API du setter clamp et trie).                     |
| `defaultBreakMinutes`  | int (défaut 60)       | pré-remplissage du formulaire de saisie.                               |

### Méthodes notables

- `getRoles()` retourne `[$this->role->value]` (compat Security).
- `isAdmin()` raccourci sur `role === Roles::ADMIN`.
- `getUserIdentifier()` retourne l'`email` (utilisé pour les logs Security).
- `getFullName()` concatène prénom + nom (ou `null` si rien).
- `getInitials()` premières lettres du prénom + nom (ou les deux premiers de l'email).
- `getExpectedDailyHours()` = `round(weeklyHours / workingDaysPerWeek, 2)` ; sert de **forfait journalier** pour les journées non chronométrées.
- `isProfileComplete()` vérifie les 6 champs RH essentiels (utile pour gating UX).
- `isContractActive(\DateTimeInterface $date)` retourne `contractStartDate !== null && $date >= contractStartDate`.

### Setters défensifs

- `setDefaultRemoteDays()` normalise et clamp les valeurs entre 1 et 7 et trie.
- `setDefaultBreakMinutes()` `max(0, …)`.

## Entité `TimeEntry`

`src/Entity/TimeEntry.php`. Une ligne par utilisateur et par date (`unique_user_date`).

### Champs

| Champ            | Type                  | Notes                                                                |
|------------------|-----------------------|----------------------------------------------------------------------|
| `id`             | int (PK)              |                                                                      |
| `user`           | ManyToOne `User`      |                                                                      |
| `date`           | DateTime              | la journée concernée (sans heure).                                   |
| `startTime`      | ?DateTime (TIME)      | obligatoire **uniquement** si `dayType=WORKED`.                      |
| `endTime`        | ?DateTime (TIME)      | obligatoire si WORKED, doit être > `startTime`.                      |
| `breakDuration`  | ?int (minutes)        | pause en minutes. `Assert\PositiveOrZero`.                           |
| `note`           | ?string(255)          | note libre.                                                          |
| `createdAt`      | datetime_immutable    |                                                                      |
| `updatedAt`      | datetime_immutable    | mis à jour manuellement dans les contrôleurs sur chaque mutation.    |
| `status`         | `Status` enum         | DRAFT / SUBMITTED / APPROVED / TO_BE_REVIEWED.                       |
| `dayType`        | `DayType` enum        | WORKED (défaut), REMOTE, PTO, UTO, OFF.                              |

### Calcul des heures (`getHoursWorked()`)

```text
WORKED          → (end - start) - break/60   (clamp ≥ 0, arrondi 0.01)
OFF             → 0
REMOTE/PTO/UTO  → user.getExpectedDailyHours() ?? 0
```

Conséquence : les jours forfaitaires héritent automatiquement des changements de profil (`weeklyHours` ou `workingDaysPerWeek` mis à jour) — les TimeEntries historiques voient leur valeur recalculée à la volée. C'est intentionnel.

### Validation `validateConsistency()`

`Assert\Callback` qui pour un `dayType === WORKED` impose `startTime` ET `endTime` non-nulls et `endTime > startTime`. Pour tout autre type, aucune contrainte horaire — on s'attend à ce que le contrôleur/le form ait nettoyé `startTime`/`endTime` (cf. `TimeEntryType::syncDayTypeFromToggle`).

## Entité `BlacklistedEmail`

`src/Entity/BlacklistedEmail.php`. Empêche la recréation d'un compte avec un email refusé par un admin.

| Champ              | Type                | Notes                                                  |
|--------------------|---------------------|--------------------------------------------------------|
| `id`               | int (PK)            |                                                        |
| `email`            | string(255) unique  | **normalisé** dans le constructeur (`trim` + `mb_strtolower`). |
| `reason`           | ?string(255)        | libre, ex : `Refusé par X`.                            |
| `blacklistedAt`    | datetime_immutable  | rempli dans le constructeur.                           |
| `blacklistedBy`    | ManyToOne `User`    | `ON DELETE SET NULL` — la blacklist survit à la suppression de l'admin. |

L'entité est **immuable côté API** (pas de setters publics, tout passe par le constructeur).

## Entité `Project`

`src/Entity/Project.php` — entité préparée pour une future imputation par projet. Actuellement non reliée à `TimeEntry`. Champs : `id`, `name`, `description?`, `isActive`.

## Enums

### `DayType`

```php
WORKED → 'Bureau'         requiresTimes=true,  isProductive=true
REMOTE → 'Télétravail'    requiresTimes=false, isProductive=true
PTO    → 'Congé payé'     requiresTimes=false, isProductive=true
UTO    → 'Congé non-payé' requiresTimes=false, isProductive=true
OFF    → 'Absent'         requiresTimes=false, isProductive=false
```

`isProductive()` détermine si la journée est comptée dans `daysWorked` (semaine) et `daysFilled` (mois). `OFF` est le seul type non-productif.

### `Status`

```php
DRAFT          → 'Brouillon'      isEditableByUser=true,  canBeSubmitted=true,  canBeUnsubmitted=false
SUBMITTED      → 'À valider'      isEditableByUser=false, canBeSubmitted=false, canBeUnsubmitted=true
APPROVED       → 'Approuvé'       isEditableByUser=false, canBeSubmitted=false, canBeUnsubmitted=false
TO_BE_REVIEWED → 'À revoir'       isEditableByUser=true,  canBeSubmitted=true,  canBeUnsubmitted=false
```

Voir [workflows.md](workflows.md) pour la machine d'état détaillée.

### `Roles`

```php
ROLE_ADMIN
ROLE_USER
```

Stocké côté DB comme la valeur de l'enum (`'ROLE_USER'`, `'ROLE_ADMIN'`).

### `ContractType`

```php
CDI            → 'CDI'
CDD            → 'CDD'
FREELANCE      → 'Freelance'
INTERNSHIP     → 'Stage'
APPRENTICESHIP → 'Alternance'
```

Aucune logique métier différenciée à ce stade — purement informatif sur le profil.

## Règles métier transverses

### Date de début de contrat

`User::contractStartDate` est une **borne basse** pour toute opération :

- `HomeController::home` redirige avec un flash si la date sélectionnée est antérieure.
- `MonthController::month` redirige vers le mois du début de contrat si tout le mois est antérieur.
- `PlanningController::create` *skip* les jours antérieurs au contrat lors d'une planification en bulk.
- `TimesheetService::countWorkingDaysInMonth` ne compte que les jours ouvrés ≥ `contractStartDate`.

### Week-ends chômés

Les jours dont `isoWeekday > 5` (samedi/dimanche) sont systématiquement exclus :

- de la vue semaine d'accueil (`HomeController` filtre à `isoWeekday <= 5`).
- de la planification en plage (`PlanningController` les skip).
- du décompte de jours ouvrés (`TimesheetService` borne `workingCount` à `min(7, …)`, et `User::setWorkingDaysPerWeek` est plafonné à 5 par l'UI).

### Forfait journalier

Pour `REMOTE`, `PTO`, `UTO`, le nombre d'heures comptabilisées est `User::getExpectedDailyHours()` = `weeklyHours / workingDaysPerWeek`. Pour `OFF`, c'est 0. Pour `WORKED`, c'est le calcul réel `end - start - break`.

### Suggestions de télétravail

`defaultRemoteDays` (ISO weekdays 1..7) sert à deux endroits :

1. `TimesheetService::buildWeekView` crée une **`virtualEntry` non persistée** au type `REMOTE` pour les jours sans entrée réelle.
2. `HomeController::buildPrefilledEntry` pré-coche le toggle « Télétravail » et bypasse le bloc start/end/break si le jour ouvert dans le form est un jour de TT prédéfini.
