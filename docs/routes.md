# Routes & contrôleurs

Référence des routes HTTP exposées par Tempo. Toutes les routes sont définies par attributs `#[Route]` sur les contrôleurs (`config/routes.yaml` se contente d'importer `routing.controllers`).

Pour générer la liste à jour : `php bin/console debug:router`.

## Accès public

| Méthode | Chemin     | Nom de route   | Contrôleur                            | Description                              |
|---------|------------|----------------|---------------------------------------|------------------------------------------|
| GET/POST| `/login`   | `app_login`    | `SecurityController::login`           | Formulaire de connexion.                 |
| GET     | `/logout`  | `app_logout`   | `SecurityController::logout`          | Intercepté par le firewall.              |
| GET/POST| `/register`| `app_register` | `RegistrationController::register`    | Inscription locale (blacklist enforced). |

## Espace utilisateur (`IS_AUTHENTICATED_FULLY`)

Toutes ces routes sont interdites aux comptes `ROLE_ADMIN` (cf. `AdminRedirectListener`).

| Méthode | Chemin                              | Nom de route               | Description                                                             |
|---------|-------------------------------------|----------------------------|-------------------------------------------------------------------------|
| GET/POST| `/`                                 | `app_home`                 | Saisie du jour sélectionné + vue semaine.                               |
| POST    | `/time-entry/{id}/delete`           | `app_time_entry_delete`    | Supprime une entrée (statut DRAFT/TO_BE_REVIEWED uniquement).            |
| POST    | `/time-entry/{id}/unsubmit`         | `app_time_entry_unsubmit`  | Repasse une entrée SUBMITTED en DRAFT.                                  |
| POST    | `/week/submit`                      | `app_week_submit`          | Soumet toutes les entrées éditables de la semaine `week=YYYY-Wnn`.      |
| GET     | `/month`                            | `app_month_current`        | Redirige vers la vue du mois courant.                                   |
| GET     | `/month/{year}/{month}`             | `app_month`                | Vue calendrier du mois.                                                 |
| GET/POST| `/profile`                          | `app_profile`              | Édition du profil + statistiques globales.                              |
| POST    | `/planning`                         | `app_planning_create`      | Crée/écrase des entrées sur une plage de dates (PTO/UTO/OFF/REMOTE).    |
| GET     | `/export`                           | `app_export`               | Télécharge le pointage de l'utilisateur (CSV ou xlsx) sur une période.  |

### Paramètres usuels

#### `app_home`

- Query `date=YYYY-MM-DD` ou `week=YYYY-Wnn` (semaine ISO).
- POST du formulaire `TimeEntryType` pour créer/mettre à jour l'entrée du jour sélectionné.

#### `app_week_submit`

- POST avec `_token` (`submit_week_<userId>`) et `week=YYYY-Wnn`.
- Itère sur les entrées de la semaine et passe en `SUBMITTED` celles qui ont `Status::canBeSubmittedByUser()`.

#### `app_planning_create`

- POST du formulaire `DayPlanningType` (`startDate`, `endDate`, `dayType`, `note?`).
- Règles d'écrasement :
    - jour `WORKED` existant → **préservé**
    - jour avec statut non éditable (SUBMITTED/APPROVED) → préservé
    - week-end (`isoWeekday > 5`) → ignoré
    - jour < `contractStartDate` → ignoré
    - sinon création (ou écrasement d'un PTO/UTO/OFF DRAFT existant)

#### `app_export`

- Query `format=xlsx|csv` (défaut `xlsx`), `from=YYYY-MM-DD`, `to=YYYY-MM-DD`.
- Période par défaut : **mois courant**. Plage libre via `from`/`to` ; si `from > to`, les bornes sont remises dans l'ordre. Dates invalides → repli sur le mois courant.
- Exporte les données de **l'utilisateur courant** uniquement. La réponse est un fichier (`Content-Disposition: attachment`) : le formulaire côté vue mois porte `data-turbo="false"` pour laisser le navigateur gérer le téléchargement.
- Détail jour par jour (date, jour, type, horaires, pause, heures, projets du jour, statut, note) + ligne de total. En xlsx : détail regroupé par année (si > 1 an) puis par semaine (n° + total hebdo), lignes colorées par type de jour, et onglet « Projets » (cumul d'heures par projet, pastille couleur).

## Espace administration (`ROLE_ADMIN`)

Préfixe `/admin`, nom de route préfixé `app_admin_`.

| Méthode | Chemin                                                            | Nom de route             | Description                                                          |
|---------|-------------------------------------------------------------------|--------------------------|----------------------------------------------------------------------|
| GET     | `/admin`                                                          | `app_admin_index`        | Redirige vers la priorité du moment (inscriptions, entrées, users).  |
| GET     | `/admin/users`                                                    | `app_admin_users`        | Liste des utilisateurs + résumé mensuel.                             |
| GET     | `/admin/users/{id}`                                               | `app_admin_user_detail`  | Détail mensuel d'un utilisateur (`year`/`month` en query).           |
| GET     | `/admin/entries`                                                  | `app_admin_entries`      | Entrées en attente d'approbation, groupées par utilisateur+semaine ISO. |
| POST    | `/admin/users/{userId}/weeks/{year}/{week}/approve`               | `app_admin_week_approve` | Approuve en bloc les entrées SUBMITTED de la semaine.                |
| POST    | `/admin/users/{userId}/weeks/{year}/{week}/review`                | `app_admin_week_review`  | Renvoie en bloc les entrées SUBMITTED de la semaine en TO_BE_REVIEWED.|
| POST    | `/admin/entries/{id}/approve`                                     | `app_admin_entry_approve`| Approuve une entrée SUBMITTED.                                       |
| POST    | `/admin/entries/{id}/review`                                      | `app_admin_entry_review` | Renvoie une entrée SUBMITTED/APPROVED en TO_BE_REVIEWED.             |
| GET     | `/admin/registrations`                                            | `app_admin_registrations`| Liste des comptes en attente de validation + blacklist.              |
| POST    | `/admin/users/{id}/approve-account`                               | `app_admin_account_approve` | Valide un compte (`isVerified = true`).                           |
| POST    | `/admin/users/{id}/reject-account`                                | `app_admin_account_reject` | Supprime le compte et ajoute l'email à la blacklist.               |
| POST    | `/admin/blacklist/{id}/remove`                                    | `app_admin_blacklist_remove` | Retire un email de la blacklist.                                 |

### Tokens CSRF des routes POST

| Action                       | Nom du token                                  |
|------------------------------|-----------------------------------------------|
| Supprimer une entrée         | `delete_entry_<entryId>`                      |
| Unsubmit une entrée          | `unsubmit_entry_<entryId>`                    |
| Soumettre une semaine        | `submit_week_<userId>`                        |
| Approuver/renvoyer une entrée| `admin_action_<entryId>`                      |
| Approuver/renvoyer semaine   | `admin_week_<userId>_<year>_<week>`           |
| Valider/refuser un compte    | `account_action_<userId>`                     |
| Retirer de la blacklist      | `blacklist_remove_<blacklistedEmailId>`       |

## Comportement transverse

### Redirection admin → `/admin`

`AdminRedirectListener` (priorité 4 sur `kernel.request`) intercepte tout admin connecté qui tente d'accéder à une route dont le nom matche un de ces préfixes :

- `app_home`
- `app_month` (et `app_month_current`)
- `app_profile`
- `app_time_entry_*`
- `app_week_*`
- `app_planning_*`

Et le redirige silencieusement vers `app_admin_index`.

### Guard date début de contrat

`HomeController::home` et `MonthController::month` redirigent (avec flash `error`) si la date/le mois ciblé est antérieur à `User::contractStartDate`.

## Routes statiques et infrastructure

- `/_profiler/*`, `/_wdt/*`, `/assets/*`, `/build/*` : exclus du firewall en dev.
- `/assets/*` : servi par AssetMapper (manifeste généré par `asset-map:compile` en prod).
