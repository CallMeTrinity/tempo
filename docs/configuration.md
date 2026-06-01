# Configuration

Toute la configuration de Tempo passe par des fichiers `.env` (au format dotenv Symfony) et `config/packages/*.yaml`.

## Fichiers `.env`

Ordre de chargement (le plus prioritaire en dernier) :

1. `.env` — défauts versionnés, à éditer pour tous les développeurs.
2. `.env.local` — overrides locaux **non versionnés** (`.gitignore`).
3. `.env.$APP_ENV` — défauts par environnement (`.env.test`, `.env.prod`).
4. `.env.$APP_ENV.local` — overrides locaux par environnement.
5. Variables d'environnement système — gagnent toujours.

Le fichier `.env.prod` est listé dans `.gitignore` et **ne doit pas contenir de secrets en clair en commit**. En production, soit le compiler avec `composer dump-env prod` (qui produit `.env.local.php`), soit injecter les variables via l'orchestrateur/host.

## Variables d'environnement

### Cadre Symfony

| Variable        | Obligatoire | Défaut (`.env`) | Description                                                       |
|-----------------|-------------|-----------------|-------------------------------------------------------------------|
| `APP_ENV`       | oui         | `dev`           | Environnement Symfony (`dev`, `test`, `prod`).                    |
| `APP_DEBUG`     | non         | `1` en dev      | Active la barre de debug, les exceptions verbeuses, le profiler.  |
| `APP_SECRET`    | oui         | vide            | Secret applicatif (signatures, CSRF, `remember me`…). À renseigner. |
| `APP_SHARE_DIR` | non         | `var/share`     | Répertoire de partage applicatif.                                 |
| `DEFAULT_URI`   | non         | `http://localhost` | URI utilisée par le routeur en CLI (commandes, mailer).       |

### Base de données

| Variable       | Obligatoire | Défaut                                                       | Description                                  |
|----------------|-------------|--------------------------------------------------------------|----------------------------------------------|
| `DATABASE_URL` | oui         | `mysql://db:db@127.0.0.1:3306/db?serverVersion=11.4.4-MariaDB&charset=utf8mb4` | DSN Doctrine. |

Le DSN doit toujours préciser `serverVersion` (MariaDB ou MySQL) — Doctrine s'en sert pour générer la bonne syntaxe.

### Messenger

| Variable                  | Obligatoire | Défaut                              | Description                              |
|---------------------------|-------------|-------------------------------------|------------------------------------------|
| `MESSENGER_TRANSPORT_DSN` | non         | `doctrine://default?auto_setup=0`   | Transport pour Symfony Messenger.        |

L'app ne dispatch aucun message à ce stade, mais le transport est configuré pour éviter une exception au démarrage si la configuration est consommée.

### Mailer

| Variable     | Obligatoire | Défaut          | Description                                                    |
|--------------|-------------|-----------------|----------------------------------------------------------------|
| `MAILER_DSN` | non         | `null://null`   | DSN du mailer. Mettre `smtp://localhost:1025` pour Mailpit.    |

Tant que `MAILER_DSN=null://null`, les emails (notamment ceux de `EmailVerifier`) sont **silencieusement jetés**.

### Test

| Variable        | Défaut                | Description                                                           |
|-----------------|-----------------------|-----------------------------------------------------------------------|
| `KERNEL_CLASS`  | `App\Kernel`          | Classe de kernel utilisée par les tests (`phpunit-bridge`).           |
| `APP_SECRET`    | `$ecretf0rt3st`       | Secret pour les tests.                                                |
| `TEST_TOKEN`    | (vide)                | Si défini (ParaTest), Doctrine ajoute un suffixe au nom de la base.   |

## Configuration `config/packages/`

### `framework.yaml`

Configuration core (HTTP cache, sessions, etc.). Pas de paramétrage spécifique au projet à connaître.

### `doctrine.yaml`

- `dbal.url` mappé sur `DATABASE_URL`.
- `orm` :
    - `validate_xml_mapping: true`
    - `naming_strategy: underscore`
    - `auto_mapping: true`
    - Mapping `App` pointe sur `src/Entity` (prefix `App\Entity`, type `attribute`).
- **En `prod`** : caches `query_cache_driver` et `result_cache_driver` mappés sur les pools `doctrine.system_cache_pool` (`cache.system`) et `doctrine.result_cache_pool` (`cache.app`).
- **En `test`** : `dbname_suffix: '_test%env(default::TEST_TOKEN)%'`.

### `security.yaml`

Détaillé dans [architecture.md](architecture.md#sécurité-configpackagessecurityyaml). Points clés :

- **Provider** : entité `App\Entity\User`, propriété de lookup **`username`** (et non `email`). Conséquence : si vous ajoutez une connexion par email, il faut adapter ce point.
- **Firewall main** : `form_login` CSRF activée, `logout` sur `/logout`.
- **Access control** : `/login` et `/register` publics, `/admin` réservé `ROLE_ADMIN`, le reste `ROLE_USER` ou `ROLE_ADMIN`.

### `asset_mapper.yaml`

- Path racine : `assets/`.
- `missing_import_mode: strict` en dev (une dépendance manquante casse le build), `warn` en prod.

### `symfonycasts_tailwind.yaml`

Configuration du bundle Tailwind. Le pipeline lit `assets/styles/app.css` et regénère le CSS scanné dans les templates Twig + JS d'`assets/`.

### `mailer.yaml`, `monolog.yaml`, `messenger.yaml`, `notifier.yaml`

Configurations par défaut Symfony. À éditer en cas d'usage avancé.

## Configuration `.symfony.local.yaml`

Utilisée par la Symfony CLI quand on lance `symfony server:start` :

```yaml
workers:
    tailwind:
        cmd: ['symfony', 'console', 'tailwind:build', '--watch']
    docker:
        cmd: ['docker-compose', 'up', '-d']
```

Ces workers sont démarrés en parallèle du serveur web. À adapter si vous voulez désactiver Tailwind watch (par ex. en CI).

## Configuration `compose.yaml` / `compose.override.yaml`

`compose.yaml` :

- Service `database` (MariaDB) avec les variables `MARIADB_DATABASE`, `MARIADB_USER`, `MARIADB_PASSWORD`, `MARIADB_ROOT_PASSWORD` (toutes défaut `db`/`root`).
- Healthcheck via `healthcheck.sh --connect --innodb_initialized`.
- Volume nommé `database_data`.

`compose.override.yaml` (chargé automatiquement par Docker Compose en local) :

- Expose le port `3306:3306` (utile pour brancher un client SQL).
- Ajoute un service `mailer` basé sur **Mailpit** (`axllent/mailpit`) avec :
    - port SMTP `1025` (interne)
    - UI HTTP `8025` (interne, à mapper côté hôte pour `localhost:8025`)
    - `MP_SMTP_AUTH_ACCEPT_ANY=1`, `MP_SMTP_AUTH_ALLOW_INSECURE=1`

Pour utiliser Mailpit en local, ajouter dans `.env.local` :

```dotenv
MAILER_DSN=smtp://localhost:1025
```

## Secrets

Le système de secrets Symfony (`config/secrets/`) **n'est pas activé** dans le projet. Pour la production, deux options :

1. Compiler les `.env` via `composer dump-env prod` et déployer `.env.local.php` (rapide, mais le secret est en clair dans le fichier).
2. Activer `secrets` et utiliser `bin/console secrets:set` (plus sûr, recommandé pour un déploiement long terme).

Voir [`docs/deployment.md`](deployment.md#secrets) pour le détail.
