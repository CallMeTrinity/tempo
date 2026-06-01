# Documentation Tempo

Documentation détaillée de l'application **Tempo** (gestion de fiches horaires).
Pour la prise en main rapide et le résumé fonctionnel, voir le [README racine](../README.md).

## Sommaire

### Mise en route

- [Getting started](getting-started.md) — installer, peupler la base, créer un admin, premier login.
- [Configuration](configuration.md) — variables d'environnement, mailer, base de données, secrets.

### Compréhension du code

- [Architecture](architecture.md) — vue d'ensemble (couches, conventions Symfony, AssetMapper, Stimulus).
- [Modèle de données](domain-model.md) — entités Doctrine, enums (`DayType`, `Status`, `Roles`, `ContractType`), règles de calcul.
- [Routes & contrôleurs](routes.md) — référence complète des routes HTTP par contrôleur.
- [Workflows](workflows.md) — cycle de vie d'une `TimeEntry`, soumission, planification, statuts.

### Exploitation

- [Guide administrateur](admin-guide.md) — modération des inscriptions, validation/refus en bulk, blacklist d'emails.
- [Développement](development.md) — bonnes pratiques, ajout de routes/entités, AssetMapper, Tailwind, Stimulus.
- [Déploiement](deployment.md) — build prod, migrations, secrets, déploiement sur Infomaniak / hébergeur PHP.

## Conventions

- Le code et la documentation sont rédigés en **français** (commentaires inline, flashs, labels Twig). Les noms d'API/de routes restent en anglais (`app_home`, `WORKED`, etc.).
- Les enums sont la source de vérité pour les **labels affichés** : `DayType::WORKED->getLabel()` retourne `« Bureau »`, etc.
- Les contrôleurs sont fins, la **logique métier vit dans `App\Service\TimesheetService`** et sur les entités (`TimeEntry::getHoursWorked()`, `User::getExpectedDailyHours()`, `Status::isEditableByUser()`).
