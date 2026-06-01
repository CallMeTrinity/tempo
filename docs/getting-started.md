# Getting started

Ce guide détaille la mise en route locale de Tempo, du clone du dépôt à la première saisie de fiche horaire.

## 1. Prérequis

| Outil          | Version       | Pourquoi                                                |
|----------------|---------------|---------------------------------------------------------|
| PHP            | ≥ 8.4         | Requis par `composer.json` (extensions `ctype`, `iconv`).|
| Composer       | ≥ 2.x         | Gestionnaire de dépendances PHP.                        |
| Docker Compose | récent        | Démarre MariaDB et Mailpit en local.                    |
| Symfony CLI    | optionnel     | Sert l'app, gère les workers (`tailwind:build --watch`).|
| Git            | —             | Clonage et workflow.                                    |

Vérifier rapidement :

```bash
php -v
composer --version
docker compose version
symfony version    # optionnel
```

## 2. Installation

```bash
git clone <repo-url> tempo
cd tempo

# Dépendances PHP : le post-install lance cache:clear, assets:install, importmap:install
composer install
```

## 3. Démarrer la base et le mailer en local

`compose.yaml` définit MariaDB. `compose.override.yaml` (chargé automatiquement) ajoute :

- l'exposition du port `3306` côté hôte
- un conteneur **Mailpit** (SMTP + UI sur `:8025`) qui capture les emails sortants

```bash
docker compose up -d
docker compose ps         # vérifier que database et mailer sont healthy
```

Le DSN par défaut dans `.env` cible cette base :

```
DATABASE_URL="mysql://db:db@127.0.0.1:3306/db?serverVersion=11.4.4-MariaDB&charset=utf8mb4"
```

Pour utiliser Mailpit comme mailer, créer un `.env.local` :

```dotenv
MAILER_DSN=smtp://localhost:1025
```

## 4. Créer le schéma

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Cela exécute tous les fichiers de `migrations/` et crée notamment les tables `user`, `time_entry`, `project`, `blacklisted_email`.

## 5. Lancer le serveur

### Avec Symfony CLI (recommandé)

```bash
symfony server:start
```

`.symfony.local.yaml` lance simultanément :

- le worker `tailwind:build --watch` (regénère `assets/styles/app.css` à la volée)
- `docker-compose up -d` (idempotent)

L'app est alors disponible sur l'URL affichée par la CLI (typiquement `https://localhost:8000`).

### Sans Symfony CLI

```bash
# Pré-construire le CSS Tailwind (une fois suffit, ou relancer après modif des templates)
php bin/console tailwind:build

# Servir avec le serveur PHP intégré
php -S 127.0.0.1:8000 -t public/
```

Pour le mode watch Tailwind dans un autre terminal :

```bash
php bin/console tailwind:build --watch
```

## 6. Créer un premier compte

L'app n'a pas de seeding automatique. Deux options :

### A. S'inscrire via l'UI

1. Aller sur `/register`.
2. Renseigner email + mot de passe (≥ 6 caractères).
3. Le compte est créé avec `role=ROLE_USER` et `isVerified=false` — l'utilisateur peut quand même se connecter, mais apparaît dans l'espace admin **Inscriptions** en attente de validation manuelle.

### B. Créer un admin via SQL

L'app ne fournit pas (encore) de commande de création d'admin. Le plus simple est de s'inscrire normalement puis de promouvoir le compte en base :

```sql
UPDATE user
SET role = 'ROLE_ADMIN', is_verified = 1
WHERE email = 'admin@example.com';
```

Pourquoi `is_verified=1` n'est pas requis fonctionnellement pour un admin (un admin n'a pas besoin de validation), mais c'est plus propre.

> Note : `AdminRedirectListener` redirige tout admin connecté vers `/admin` dès qu'il tente d'accéder à une page utilisateur (`/`, `/month`, `/profile`, etc.).

## 7. Renseigner le profil

À la première connexion, aller sur `/profile` et renseigner :

- **Prénom**, **Nom**, **Poste**
- **Type de contrat** (CDI, CDD, Freelance, Stage, Alternance)
- **Heures hebdomadaires** (ex : 35, 37.5, 39)
- **Début du contrat** (date à partir de laquelle la saisie est autorisée)
- **Jours travaillés par semaine** (1–5, week-ends toujours chômés)
- **Pause par défaut** (en minutes, sert au pré-remplissage des journées WORKED)
- **Jours de télétravail prédéfinis** (cases Lun–Ven) — ces jours seront pré-cochés en `REMOTE` à l'ouverture du formulaire de saisie

Sans ces informations, le calcul des forfaits journaliers (`User::getExpectedDailyHours()`) renvoie `null` et les statistiques mensuelles ne s'affichent pas correctement.

## 8. Saisir sa première journée

1. Aller sur `/` (Home).
2. La date du jour est sélectionnée par défaut. Naviguer dans la semaine via les cellules.
3. Cocher **Télétravail** pour basculer en forfait journalier, sinon renseigner début/fin/pause.
4. Sauvegarder. L'entrée est créée au statut `DRAFT`.
5. Une fois la semaine remplie, cliquer **Soumettre la semaine** pour passer toutes les entrées éditables en `SUBMITTED`.

Voir [workflows.md](workflows.md) pour le détail du cycle de vie.

## Dépannage

| Symptôme                                         | Cause probable                                              | Solution                                                                 |
|--------------------------------------------------|-------------------------------------------------------------|--------------------------------------------------------------------------|
| `Connection refused` sur la base                 | Docker pas démarré                                          | `docker compose up -d`                                                   |
| Pages sans style                                 | `tailwind:build` jamais lancé                               | `php bin/console tailwind:build` ou `symfony server:start`               |
| `Le ... est antérieur à votre date de début ...` | Date sélectionnée avant `contractStartDate`                 | Renseigner ou avancer la date dans `/profile`.                           |
| Login OK mais redirigé vers `/admin`             | Compte promu `ROLE_ADMIN`                                   | C'est le comportement attendu : un admin ne saisit pas ses propres heures.|
| Email d'inscription jamais reçu                  | `MAILER_DSN=null://null` (défaut)                           | Pointer vers Mailpit (`smtp://localhost:1025`) puis ouvrir `:8025`.       |
