# Workflows

Ce document décrit le cycle de vie d'une `TimeEntry`, la planification en plage de dates, ainsi que la modération des comptes.

## Cycle de vie d'une `TimeEntry`

### États (`Status` enum)

```
                                    ┌─────────────────────┐
                                    │   (création UI)     │
                                    └──────────┬──────────┘
                                               │
                                               ▼
                          ┌──────────────────────────────────┐
                          │             DRAFT                │
                          │       (« Brouillon »)            │
                          │ Éditable. Supprimable.           │
                          └──────────────────┬───────────────┘
                                             │ user submit
                                             ▼
                          ┌──────────────────────────────────┐
                          │           SUBMITTED              │
                          │       (« À valider »)            │
                          │ Verrou utilisateur (lecture).    │
                          │ Peut être retirée (unsubmit)     │
                          │  → revient en DRAFT.             │
                          └────────┬───────────────┬─────────┘
                                   │ admin approve │ admin review
                                   ▼               ▼
                  ┌────────────────────────┐  ┌────────────────────────┐
                  │       APPROVED         │  │   TO_BE_REVIEWED       │
                  │     (« Approuvé »)     │  │     (« À revoir »)     │
                  │ Verrou définitif.      │  │ Éditable par user.     │
                  │ Plus aucune action     │  │ Peut être resoumise.   │
                  │ utilisateur possible.  │  │                        │
                  └────────────────────────┘  └─────────┬──────────────┘
                                                       │ user edit + submit
                                                       └────► SUBMITTED
```

### Tableau récapitulatif des transitions

| État              | Éditable user  | Soumettable     | Unsubmit        | Action admin            |
|-------------------|----------------|-----------------|-----------------|-------------------------|
| `DRAFT`           | oui            | oui → SUBMITTED | —               | —                       |
| `SUBMITTED`       | non            | —               | oui → DRAFT     | approve, review         |
| `APPROVED`        | non            | —               | —               | review (re-ouverture)   |
| `TO_BE_REVIEWED`  | oui            | oui → SUBMITTED | —               | —                       |

### Méthodes d'enum à connaître

```php
$status->isEditableByUser()       // DRAFT, TO_BE_REVIEWED
$status->canBeSubmittedByUser()   // DRAFT, TO_BE_REVIEWED
$status->canBeUnsubmittedByUser() // SUBMITTED uniquement
```

Toutes les actions de mutation (delete, unsubmit, submit, approve, review) vérifient ces prédicats **avant** d'agir et affichent un flash si l'état n'est pas valide.

### Soumission en bloc (semaine)

`POST /week/submit` (`app_week_submit`) avec `week=YYYY-Wnn` :

1. Récupère toutes les `TimeEntry` du `userId` connecté entre le lundi et le dimanche.
2. Pour chaque entrée, si `Status::canBeSubmittedByUser()` est vrai → bascule en `SUBMITTED`.
3. Les autres sont comptées comme « skipped ».
4. Flash récapitulatif (`x soumises`, `y déjà soumises ou approuvées`).

Un compte admin qui tenterait cette action reçoit un flash d'erreur (`Un admin ne soumet pas ses heures.`).

### Approbation en bloc (semaine, côté admin)

`POST /admin/users/{userId}/weeks/{year}/{week}/approve` (`app_admin_week_approve`) et son pendant `review` :

1. Charge toutes les entrées de l'utilisateur entre le lundi et le dimanche.
2. Pour chaque entrée **en `SUBMITTED`** → bascule en `APPROVED` (ou `TO_BE_REVIEWED`).
3. Les autres états sont préservés.
4. Flash récapitulatif (`Semaine N (Nom) : X entrées approuvées.`).

## Planification d'une plage de jours

`POST /planning` (`app_planning_create`) prend en entrée `startDate`, `endDate`, `dayType` (REMOTE/PTO/UTO/OFF) et une note optionnelle.

### Décisions par jour

Pour chaque jour entre `startDate` et `endDate` (inclus) :

| Cas                                                                    | Action                |
|------------------------------------------------------------------------|-----------------------|
| Week-end (`isoWeekday > 5`)                                            | skip                  |
| Avant `User::contractStartDate`                                        | skip                  |
| Entrée existante avec `dayType=WORKED`                                 | **conservée**         |
| Entrée existante avec `Status` non éditable (SUBMITTED/APPROVED)       | **conservée**         |
| Entrée existante éditable + non-WORKED                                 | **écrasée** (type/note remplacés, heures vidées) |
| Aucune entrée existante                                                | **créée** au statut `DRAFT` |

Le flash final résume : `N jour(s) planifié(s) en <Label> (X remplacé(s), Y travaillé(s) conservé(s), Z avant contrat, …)`.

### Différence avec la saisie quotidienne

| Saisie quotidienne (Home)         | Planification (Planning)            |
|-----------------------------------|-------------------------------------|
| Une journée à la fois.            | Une plage de dates.                 |
| WORKED ou REMOTE.                 | REMOTE, PTO, UTO, OFF (pas WORKED). |
| Respecte/édite la note via form.  | Une seule note appliquée à tous les jours créés/écrasés. |

## Suggestions de télétravail (jours prédéfinis)

Configuration sur `/profile` → `Jours de télétravail prédéfinis` (ChoiceType Lun–Ven multi).

### Effet 1 — Pré-cochage du formulaire de saisie

Dans `HomeController::buildPrefilledEntry`, si l'`isoWeekday` du jour ouvert est dans `defaultRemoteDays` :

- type pré-positionné à `REMOTE`
- pas de pré-remplissage de start/end/break

Sinon :

- type `WORKED`
- `startTime` à `09:00`
- `endTime` calculé = `09:00 + expectedDailyHours + defaultBreakMinutes`
- `breakDuration` = `defaultBreakMinutes`

### Effet 2 — Cellules « virtuelles » dans la vue semaine

`TimesheetService::buildWeekView` ajoute pour chaque jour sans entrée et sans contrat antérieur, **et** si le jour est dans `defaultRemoteDays`, une **`virtualEntry` non persistée** au type `REMOTE`. Les templates Twig peuvent ainsi afficher visuellement la suggestion sans créer de ligne en base. Cliquer dessus ouvre le formulaire avec le toggle Télétravail pré-coché.

## Inscriptions et modération de compte

### Flux d'inscription

`POST /register` (`app_register`) :

1. Form `RegistrationFormType` validé (email, `plainPassword`, `agreeTerms`).
2. **Blacklist check** (`BlacklistedEmailRepository::existsByEmail`) — si l'email est blacklisté, renvoyer un flash d'erreur et ré-afficher le form.
3. Hash du mot de passe.
4. Dérivation automatique :
    - `firstName` = partie locale avant le premier `.` de l'email, capitalisée.
    - `username` = partie locale avant `@`.
5. `role = ROLE_USER`, `isVerified = false`.
6. Persistence + login automatique (`Security::login`).
7. Flash info : « Compte créé. Un administrateur validera votre compte sous peu. »

À ce stade, l'utilisateur **peut utiliser l'app** (saisir, soumettre). Le statut `isVerified` ne bloque pas l'accès — il sert d'indicateur de modération côté admin.

### Validation par un admin

Page `/admin/registrations` (`app_admin_registrations`) :

- Liste les comptes `isVerified=false` qui ne sont pas admins.
- Pour chaque compte, deux actions :
    - **Valider** (`account_approve`) : `isVerified = true`.
    - **Refuser** (`account_reject`) : supprime l'utilisateur et ses `TimeEntries`, puis crée un `BlacklistedEmail` avec l'email de l'utilisateur supprimé, en référencement à l'admin courant comme `blacklistedBy` et un `reason` automatique (`Refusé par <admin>`).

> Cascade : Doctrine n'est pas configuré pour cascader la suppression des `TimeEntry` ; le contrôleur les supprime explicitement avant de retirer l'utilisateur pour éviter la violation de contrainte FK.

### Blacklist d'emails

Page `/admin/registrations` liste également les emails blacklistés.

Actions disponibles :

- **Retirer** (`blacklist_remove`) : supprime la ligne `BlacklistedEmail`. L'utilisateur peut alors créer un nouveau compte avec cet email.

L'entité est créée **uniquement** par le rejet d'inscription (pour l'instant) ; il n'y a pas d'UI pour ajouter manuellement un email à la blacklist.

## Cas particuliers

### Comptes admin

- Sont automatiquement redirigés vers `/admin` s'ils naviguent vers une page utilisateur (voir `AdminRedirectListener`).
- Ne sont jamais comptés dans `findUnverified()` ni dans `countPendingApproval()` (les entrées des admins sont ignorées dans la liste d'approbation).
- Ne peuvent ni soumettre leurs heures (`app_week_submit` retourne un flash d'erreur) ni être validés/refusés via le flux registration.

### Profil incomplet

`User::isProfileComplete()` est *informatif* pour les templates : aucun gating n'est imposé côté contrôleur, mais sans `weeklyHours` et `workingDaysPerWeek`, `getExpectedDailyHours()` retourne `null` et toutes les statistiques mensuelles sont à 0/null. Il est fortement recommandé de gater la saisie sur ce prédicat dans la couche présentation.

### Avant contrat

Aucune saisie ne peut viser une date < `contractStartDate`. Les contrôleurs redirigent avec un flash, et la planification skip ces jours en bulk.
