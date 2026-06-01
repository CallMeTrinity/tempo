# Guide administrateur

Ce guide cible un opérateur disposant d'un compte avec le rôle `ROLE_ADMIN`. Pour la création initiale d'un admin, voir [getting-started.md](getting-started.md#b-créer-un-admin-via-sql).

## Comportement général

- Un admin est **redirigé en permanence vers `/admin`** dès qu'il navigue vers une page utilisateur (`/`, `/month`, `/profile`, `/planning`, etc.) — cf. `AdminRedirectListener`. Les routes admin sont donc le seul univers accessible à un compte admin.
- L'admin **ne saisit pas ses propres heures** et ne peut pas être validé via le flux d'inscription.

## La page d'accueil admin (`/admin`)

`AdminController::index` choisit la première priorité parmi :

1. Comptes en attente de validation → redirige vers `/admin/registrations`.
2. Entrées en attente d'approbation → redirige vers `/admin/entries`.
3. Sinon → `/admin/users`.

Cela offre un *workflow guided* : l'admin atterrit toujours sur la file la plus urgente.

## Modération des inscriptions (`/admin/registrations`)

### Liste des comptes en attente

`UserRepository::findUnverified()` retourne les utilisateurs avec `isVerified=false` qui ne sont pas admins, triés par `createdAt ASC` (les plus anciens en haut).

### Actions

Pour chaque compte :

- **Valider** (`POST /admin/users/{id}/approve-account`) :
    - Le compte passe `isVerified=true`.
    - Token CSRF : `account_action_<userId>`.
    - Le compte continue à fonctionner comme avant ; la validation est principalement un acte de modération.

- **Refuser** (`POST /admin/users/{id}/reject-account`) :
    - Supprime toutes les `TimeEntries` du compte, puis le compte lui-même.
    - Crée une entrée `BlacklistedEmail` avec l'email rejeté (lowercased), l'admin courant comme `blacklistedBy`, et un `reason` automatique (`Refusé par <admin>`).
    - Token CSRF : `account_action_<userId>`.

### Blacklist d'emails

La page `/admin/registrations` liste aussi les emails blacklistés (DESC sur `blacklistedAt`). Pour chacun :

- **Retirer** (`POST /admin/blacklist/{id}/remove`) — retire l'email de la liste. L'utilisateur peut alors recréer un compte avec cet email.
- Token CSRF : `blacklist_remove_<blacklistedEmailId>`.

> Aucun ajout manuel n'est exposé par l'UI : la blacklist se peuple uniquement par le rejet d'inscription. Pour ajouter manuellement, passer par SQL ou un fixture.

## Validation des fiches horaires (`/admin/entries`)

### Vue groupée

Les entrées `SUBMITTED` (hors entrées d'admins) sont chargées via `TimeEntryRepository::findPendingApproval()` puis regroupées en mémoire par **`utilisateur + semaine ISO`** :

```
groups = [
  {
    user, year, week, weekStart (lundi), weekEnd (vendredi),
    entries[], totalHours, daysCount,
  },
  …
]
```

L'UI présente donc des « cartes semaine », pas des entrées individuelles, ce qui colle au mode opératoire courant (un employé soumet sa semaine complète, l'admin la valide d'un coup).

### Actions par semaine

| Action            | Route                                                              | Effet sur les entrées SUBMITTED de la semaine                       |
|-------------------|--------------------------------------------------------------------|---------------------------------------------------------------------|
| Approuver semaine | `POST /admin/users/{userId}/weeks/{year}/{week}/approve`           | `Status` → `APPROVED`                                               |
| Renvoyer semaine  | `POST /admin/users/{userId}/weeks/{year}/{week}/review`            | `Status` → `TO_BE_REVIEWED` (redonne la main à l'utilisateur)       |

Token CSRF : `admin_week_<userId>_<year>_<week>`.

Les entrées qui ne sont pas en `SUBMITTED` (déjà approuvées, en draft, …) sont préservées telles quelles. Le flash récapitule combien d'entrées ont été touchées.

### Actions par entrée individuelle

Toujours disponibles si l'on a besoin de granularité plus fine :

- `POST /admin/entries/{id}/approve` — accepte uniquement `SUBMITTED`.
- `POST /admin/entries/{id}/review` — accepte `SUBMITTED` ou `APPROVED`.
- Token CSRF : `admin_action_<entryId>`.

`review` sur une entrée déjà approuvée la **ré-ouvre** côté utilisateur (utile pour demander une correction post-validation).

## Vue d'ensemble des utilisateurs (`/admin/users`)

Liste les utilisateurs (hors l'admin courant) avec, pour chaque, ses stats du mois en cours :

- `monthTotal` (heures saisies, incluant les forfaits)
- `expectedHours` (sur la base de `expectedDailyHours × jours ouvrés ≥ contractStart`)
- `overtime` / `deficit`
- `daysFilled` / `expectedDays`

Source : `TimesheetService::computeMonthlyStats($user, $year, $month)`.

## Fiche utilisateur (`/admin/users/{id}`)

Permet de naviguer mois par mois (`?year=&month=`) pour un utilisateur donné. Affiche :

- Les stats mensuelles complètes.
- Les 60 dernières entrées (`TimeEntryRepository::findRecentByUser($user, 60)`).
- Des liens prev/next pour naviguer entre mois.

Aucune action de mutation sur cette page : pour modifier le statut, passer par `/admin/entries`.

## Compteurs en topbar / sidebar

Les templates affichent en permanence :

- `pendingCount` = `TimeEntryRepository::countPendingApproval()` (entrées `SUBMITTED`, hors admins).
- `unverifiedCount` = `UserRepository::countUnverified()` (comptes en attente, hors admins).

C'est ce qui pilote la priorisation de la landing `/admin`.

## Sécurité opérationnelle

- Toutes les actions de mutation exigent un **token CSRF nommé** (voir [routes.md](routes.md#tokens-csrf-des-routes-post)).
- Toutes les pages admin sont gardées par `#[IsGranted('ROLE_ADMIN')]` au niveau classe et `access_control` dans `security.yaml` (`^/admin → ROLE_ADMIN`).
- Pas d'API anonyme : tout passe par session form_login.

## Anti-patterns à éviter

- **Ne pas valider en bulk depuis SQL** : passer par les routes admin pour conserver l'audit (`updatedAt`, et un futur log si ajouté).
- **Ne pas supprimer un user directement en base** : la cascade `TimeEntry` n'est pas configurée côté Doctrine, ce qui produirait une violation FK. La route `reject-account` gère la suppression dans le bon ordre.
- **Ne pas promouvoir un compte en `ROLE_ADMIN` après usage prolongé** : ce compte ne pourra plus saisir ses heures, et toutes ses anciennes saisies seront ignorées des listes d'approbation.
