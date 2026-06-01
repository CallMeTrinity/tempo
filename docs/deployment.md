# Déploiement

Ce guide cible un déploiement sur un hébergement PHP mutualisé/VPS (le projet est actuellement provisionné chez **Infomaniak** d'après `.env.prod`).

## Pré-requis serveur

| Composant   | Version       | Notes                                                       |
|-------------|---------------|-------------------------------------------------------------|
| PHP         | ≥ 8.4         | Extensions : `ctype`, `iconv`, `intl`, `pdo_mysql`, `opcache` (recommandé). |
| Composer    | ≥ 2           | Pour `composer install --no-dev --optimize-autoloader`.     |
| MariaDB     | ≥ 11.4        | (ou MySQL 8 — adapter `serverVersion`).                     |
| Serveur web | nginx/Apache  | Document root sur `public/`.                                |
| Node        | non requis    | Tailwind est en CLI PHP via le bundle.                      |

## Variables d'environnement de prod

Le fichier `.env.prod` (non versionné, en `.gitignore`) doit définir au minimum :

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<32+ caractères aléatoires>
DATABASE_URL="mysql://<user>:<password>@<host>:3306/<db>?serverVersion=11.4.4-MariaDB&charset=utf8mb4"
MAILER_DSN=<DSN smtp ou null://null si pas de mailer>
```

**Ne jamais commiter ce fichier.** Pour générer un secret :

```bash
openssl rand -hex 32
```

### Variantes

- **MySQL 8** : changer `serverVersion=8.0.32` dans le DSN.
- **Plusieurs environnements** (staging/prod) : utiliser `APP_ENV=staging` et `config/packages/staging/*.yaml` au besoin.

## Étapes de déploiement

### 1. Copier les sources sur le serveur

```bash
rsync -avz --exclude-from=.gitignore --exclude=.git ./ deploy@host:/var/www/tempo/
# (ou Git pull si le serveur tire depuis le repo)
```

### 2. Installer les dépendances prod

```bash
cd /var/www/tempo
composer install --no-dev --optimize-autoloader --no-interaction
```

`--optimize-autoloader` génère un autoload de production. `--no-dev` exclut PHPUnit, Maker, Web Profiler.

### 3. Compiler les assets

```bash
# Compile le CSS Tailwind avec minification
php bin/console tailwind:build --minify

# Compile l'asset map (génère public/assets/ et le manifeste)
php bin/console asset-map:compile
```

`public/assets/` est ignoré par `.gitignore` : c'est un artefact de build.

### 4. Migrer la base

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Warmup du cache prod

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup
```

### 6. Permissions

```bash
chown -R www-data:www-data var/ public/assets/
chmod -R u+w var/
```

### 7. Configuration du serveur web

#### nginx (extrait)

```nginx
server {
    listen 80;
    server_name tempo.example.com;
    root /var/www/tempo/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    error_log /var/log/nginx/tempo_error.log;
    access_log /var/log/nginx/tempo_access.log;
}
```

#### Apache

Le `composer install` Symfony Flex pose habituellement un `.htaccess` dans `public/`. Sinon, installer `symfony/apache-pack`.

## Secrets

### Option 1 — `.env.local.php` (rapide)

```bash
composer dump-env prod
```

Produit un fichier `.env.local.php` contenant un tableau PHP des variables compilées. Symfony le préfère aux `.env` (chargement plus rapide). À déployer avec les sources, **en dehors du repo**.

### Option 2 — Symfony Secrets (recommandé long terme)

```bash
# En local (dev) :
php bin/console secrets:generate-keys
# Ajoute les clés générées au déploiement (config/secrets/prod/).
# La clé décryptée (`prod.decrypt.private.php`) est dans .gitignore.

php bin/console secrets:set APP_SECRET --env=prod
php bin/console secrets:set DATABASE_URL --env=prod
```

Sur le serveur, soit déployer `prod.decrypt.private.php`, soit définir `SYMFONY_DECRYPTION_SECRET`.

Documentation : <https://symfony.com/doc/current/configuration/secrets.html>.

## Healthcheck après déploiement

```bash
# La home doit renvoyer un 302 vers /login pour un anonyme
curl -I https://tempo.example.com/
# → HTTP/1.1 302 Found
# → Location: /login

# Le login doit s'afficher
curl -I https://tempo.example.com/login
# → HTTP/1.1 200 OK

# Tester la base depuis le serveur
APP_ENV=prod php bin/console doctrine:query:sql "SELECT 1"
```

## Rollback

Tempo n'a pas de système de release automatisé. En cas de souci :

1. Restaurer le code de la version précédente (Git checkout ou rsync inverse).
2. **Si une migration a tourné** : utiliser `doctrine:migrations:execute <Version> --down` pour annuler, ou restaurer un dump SQL pris avant migration.
3. `cache:clear && cache:warmup`.

## Maintenance courante

### Mettre à jour une dépendance

```bash
composer outdated
composer update <package>
```

Tester en dev, refaire un déploiement complet.

### Tourner une commande sur la prod

```bash
APP_ENV=prod php bin/console <commande>
```

### Sauvegarder la base

```bash
mysqldump -u<user> -p<password> <db> > backup_$(date +%F).sql
```

## Notes spécifiques Infomaniak

- Les variables sensibles peuvent être placées dans le panneau Infomaniak (« Environnement ») plutôt que dans `.env.prod`.
- `composer dump-env prod` est compatible avec le déploiement Git Infomaniak.
- Tailwind requiert que PHP CLI puisse écrire dans `public/assets/` — vérifier les droits du déploiement.
