# Développement

Conventions et recettes pour contribuer au code de Tempo.

## Conventions de code

- **PHP 8.4**, typage strict implicite (déclaration des types sur tous les paramètres et retours).
- **PSR-4** : `App\` → `src/`, `App\Tests\` → `tests/`.
- **Indentation** : 4 espaces, LF, UTF-8, newline finale (`.editorconfig`).
- **Doctrine** : annotations en **attributs PHP** (`#[ORM\Entity]`, jamais XML/YAML).
- **Langue** : français pour les commentaires, labels Twig et messages flash. Anglais pour les noms d'API et de routes.
- **Style Symfony** :
    - Contrôleurs minces, logique métier dans `App\Service\*` ou sur les entités.
    - `IsGranted` au niveau classe quand toutes les routes ont la même contrainte.
    - Les setters retournent `static` pour permettre le chaining.

## Ajouter une route

1. Créer ou éditer un contrôleur dans `src/Controller/`.
2. Ajouter `#[Route('/path', name: 'app_route_name', methods: ['GET'])]` sur la méthode.
3. Pour une protection d'accès :
    - `#[IsGranted('IS_AUTHENTICATED_FULLY')]` au niveau classe pour l'espace utilisateur.
    - `#[IsGranted('ROLE_ADMIN')]` au niveau classe pour `/admin/*`.
4. Si la route est destinée à un utilisateur et NON à un admin → l'ajouter dans `AdminRedirectListener::USER_ROUTES` (préfixe).
5. Vérifier : `php bin/console debug:router`.

### Action POST avec CSRF manuel

Pattern récurrent dans le code :

```php
#[Route('/foo/{id}/action', name: 'app_foo_action', methods: ['POST'])]
public function action(Foo $foo, Request $request, EntityManagerInterface $em): Response
{
    if ($foo->getUser() !== $this->getUser()) {
        throw $this->createAccessDeniedException();
    }
    if (!$this->isCsrfTokenValid('action_foo_' . $foo->getId(), (string) $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('CSRF invalide.');
    }

    // ... mutation ...
    $em->flush();
    $this->addFlash('success', 'OK.');

    return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
}
```

Côté Twig, le token se génère avec `csrf_token('action_foo_' ~ entry.id)`.

## Ajouter une entité

1. `php bin/console make:entity` (interactive) ou créer manuellement la classe dans `src/Entity/`.
2. Ajouter les attributs Doctrine (`#[ORM\Entity]`, `#[ORM\Column]`).
3. Pour les contraintes d'unicité : `#[ORM\UniqueConstraint]` côté DB et `#[UniqueEntity]` côté validateur.
4. Pour des règles de cohérence inter-champs : `#[Assert\Callback]` sur une méthode `validate*` (cf. `TimeEntry::validateConsistency`).
5. Générer la migration : `php bin/console make:migration`.
6. Vérifier le SQL généré, ajuster si besoin (renommer les contraintes, etc.).
7. Appliquer : `php bin/console doctrine:migrations:migrate`.
8. Créer le repository correspondant (`src/Repository/<Name>Repository.php` étendant `ServiceEntityRepository`).

## Ajouter un enum

1. Créer dans `src/Enum/`, typé `string` (le projet utilise des string-backed enums).
2. Définir `getLabel()` (libellé français pour l'UI) et toute méthode de prédicat utile (`isFoo()`, `canBar()`).
3. L'utiliser dans une entité : `#[ORM\Column(enumType: MyEnum::class)]`.
4. Dans un form : `EnumType` avec `'class' => MyEnum::class` et `'choice_label' => fn ($e) => $e->getLabel()`.

## Ajouter un form

Pattern utilisé dans le projet :

```php
class FooType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('field', TextType::class, [
                'label' => 'Libellé',
                'required' => false,
                'attr' => ['placeholder' => '…'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Foo::class,
            'csrf_protection' => false, // si page derrière IsGranted
        ]);
    }
}
```

- `data_class` = `null` pour un form non lié à une entité (cf. `DayPlanningType`).
- Synchronisation entre un champ non mappé et l'entité : utiliser un event listener `POST_SUBMIT` (cf. `TimeEntryType::syncDayTypeFromToggle`).

## Ajouter un Stimulus controller

1. Créer `assets/controllers/<name>_controller.js`.
2. Pas besoin de l'enregistrer manuellement — `@symfony/stimulus-bundle` le détecte automatiquement.
3. Brancher dans Twig :

```twig
<div data-controller="my-controller">
    <input data-action="change->my-controller#apply"
           data-my-controller-target="checkbox">
</div>
```

## Modifier les styles

- Source : `assets/styles/app.css` + Tailwind utility classes dans les templates.
- En dev : si Symfony CLI tourne, Tailwind reconstruit automatiquement.
- En CI / build manuel : `php bin/console tailwind:build`.
- Pour produire le bundle prod compressé : `php bin/console tailwind:build --minify`.

## Tests

```bash
php bin/phpunit
```

- Bootstrap : `tests/bootstrap.php` charge `.env`.
- `phpunit.dist.xml` est en mode strict : `failOnDeprecation`, `failOnNotice`, `failOnWarning`. **Toute notice/warning fait échouer la suite.**
- Source coverage scope : `src/`.
- `tests/` ne contient que le bootstrap pour le moment — il n'y a pas encore de cas de test.

### Premier test à écrire

Bonnes cibles candidates :

- `TimesheetServiceTest` — tester `computeWeeklyStats`, `computeMonthlyStats`, `normalizeMonday`.
- `DayTypeTest` / `StatusTest` — couvrir les prédicats.
- `TimeEntryTest::testGetHoursWorked` — chaque branche (WORKED, OFF, REMOTE…).

Squelette type pour un test unitaire :

```php
namespace App\Tests\Service;

use App\Service\TimesheetService;
use PHPUnit\Framework\TestCase;

final class TimesheetServiceTest extends TestCase
{
    public function testNormalizeMondayReturnsMondayAtZeroHours(): void
    {
        $repo = $this->createMock(\App\Repository\TimeEntryRepository::class);
        $service = new TimesheetService($repo);

        $monday = $service->normalizeMonday(new \DateTimeImmutable('2026-05-21')); // jeudi
        self::assertSame('2026-05-18 00:00:00', $monday->format('Y-m-d H:i:s'));
    }
}
```

## Doctrine en pratique

### Lookup d'entrées sur une plage

`TimeEntryRepository::findByUserBetween` est l'API canonique :

```php
$entries = $entryRepo->findByUserBetween(
    $user,
    \DateTime::createFromImmutable($monday),
    \DateTime::createFromImmutable($sunday),
);
```

Les bornes sont **inclusives** (`BETWEEN :from AND :to` en SQL).

### Pattern `existsByX`

Préférer un `SELECT COUNT(b.id) > 0` à un `findOneBy` pour les vérifications binaires (cf. `BlacklistedEmailRepository::existsByEmail`).

### Update d'horodatage

Le projet n'utilise pas (encore) Gedmo Timestampable. **Les contrôleurs sont responsables** de mettre `updatedAt` à jour manuellement :

```php
$entity->setUpdatedAt(new \DateTimeImmutable());
$em->flush();
```

Une migration vers un EventListener Doctrine (`prePersist` / `preUpdate`) serait un *good first issue*.

## Tailwind et templates

- Toutes les classes utilisées doivent apparaître dans des fichiers que Tailwind scanne. Vérifier `tailwind.config` si vous ajoutez de nouveaux répertoires.
- Composants réutilisables → `templates/components/`, inclus via `{% include 'components/foo.html.twig' with { … } %}`.

## Debug

```bash
php bin/console debug:router
php bin/console debug:container
php bin/console debug:event-dispatcher kernel.request   # voir les listeners
php bin/console doctrine:schema:validate
php bin/console doctrine:mapping:info
```

En dev, le Web Profiler (`/_profiler/`) est disponible (cf. `symfony/web-profiler-bundle` en `require-dev`).

## Workflow Git suggéré

1. Brancher depuis `main` (`git switch -c feat/<sujet>` ou `fix/<sujet>` ou `chore/<sujet>`).
2. Commits préfixés selon le style observé dans `git log` : `feat:`, `fix:`, `chore:`, etc.
3. `php bin/phpunit` avant push.
4. MR sur GitLab (`gitlab.com/antonin.p/symfony-tempo`).
