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

## Phase 1 — Modèle de données

Nouveaux types
- src/Enum/DayType.php : enum avec cases WORKED, REMOTE, PTO, OFF + getLabel() FR

TimeEntry
- Ajouter #[ORM\Column(enumType: DayType::class)] private DayType $type = DayType::WORKED;
- Rendre startTime/endTime/breakDuration nullable (les jours REMOTE/PTO/OFF n'en ont pas besoin)

User
- #[ORM\Column] private int $workingDaysPerWeek = 5;
- #[ORM\Column(type: Types::JSON)] private array $defaultRemoteDays = []; (stockera les iso-weekdays 1-7, ex : [3] pour mercredi)

Migration
- make:migration puis migrations:migrate

  ---                                                                                                                                                                                                                                                                                                                  
## Phase 2 — Logique métier

TimeEntry::getHoursWorked()
- Si type = WORKED → calcul actuel (endTime - startTime - break/60)
- Si type = REMOTE ou PTO → return user.expectedDailyHours
- Si type = OFF → return 0

User helpers à ajouter
```php
getExpectedDailyHours(): ?float  // weeklyHours / workingDaysPerWeek (déjà existant, à corriger pour utiliser workingDaysPerWeek)                                                                                                                                                                                      
isContractActive(\DateTimeInterface $date): bool  // date >= contractStartDate                                                                                                                                                                                                                                       
getDefaultRemoteWeekdays(): array  // accesseur sécurisé
```
Nouveau service src/Service/TimesheetService.php (sortir la logique du controller)   
```php

buildWeekView(User $user, \DateTime $weekStart): array                                                                                                                                                                                                                                                                 
// retourne 7 cells : date, entry|null, isWorkingDay, isFuture, isBeforeContract, isPredefinedRemote

computeWeeklyStats(User $user, array $entries): array                                                                                                                                                                                                                                                                  
// retourne: workedHours, overtimeHours, deficitHours, daysWorked, progress%

computeMonthlyStats(User $user, int $year, int $month): array                                                                                                                                                                                                                                                          
// total mois, jours saisis, jours attendus, overtime cumulé
```
TimeEntryRepository
- ```findByUserForMonth(User $user, int $year, int $month): array```

  ---                                                                                                                                                                                                                                                                                                                  
## Phase 3 — Formulaires

TimeEntryType
- Ajouter type (EnumType avec DayType)
- Mettre startTime/endTime/breakDuration en required => false
- Validation côté Entity : si type === WORKED alors start/end requis (contrainte Assert\Callback)

UserProfileType
- workingDaysPerWeek : IntegerType (min 1, max 7)
- defaultRemoteDays : ChoiceType multiple avec choix [Lundi => 1, Mardi => 2, ..., Dimanche => 7], expanded: true, multiple: true (cases à cocher)

  ---                                                                                                                                                                                                                                                                                                                    
## Phase 4 — Controller

HomeController::home()
1. Lire ?week=YYYY-Wnn (format ISO) en plus de ?date=
    - Si absent → semaine en cours
    - Calcul du lundi de la semaine demandée

2. Refuser une date < user.contractStartDate
    - Si demandée → flash error + redirect semaine du contractStart

3. Pour la semaine affichée, pré-remplir (en mémoire, pas en DB) les jours qui sont                                                                                                                                                                                                                                    
   dans defaultRemoteDays et qui n'ont pas encore d'entrée → entries virtuelles affichables
    - L'utilisateur peut les confirmer/modifier (création réelle en DB au submit)

4. Calculer overtime via TimesheetService et passer au template

Nouvelle route   
```php
#[Route('/month/{year<\d{4}>}/{month<\d{1,2}>}', name: 'app_month')]                                                                                                                                                                                                                                                   
public function month(int $year, int $month, ...): Response
```
TimeEntryType form handling
- Si type !== WORKED au submit : forcer start/end/break à null avant persist
- Pré-remplir startTime/endTime/breakDuration depuis user.expectedDailyHours quand type === WORKED et qu'on est en création

  ---                                                                                                                                                                                                                                                                                                                    
## Phase 5 — UI / Templates

Nouveau composant templates/components/week_nav.html.twig                                                                                                                                                                                                                                                            
◀ Semaine précédente   |   Semaine 19 · 04 → 10 mai 2026   |   Semaine suivante ▶   |   Aujourd'hui                                                                                                                                                                                                                    
(boutons désactivés si avant contractStart ou semaine future)

Day card ts-day : variants visuels
- is-worked (actuel)
- is-remote (badge "TT")
- is-pto (badge "Congé")
- is-off (jour off, gris, non-cliquable)
- is-suggested (predefined remote pas encore validé, opacité 70%, pointillé)

Hero
- Ajouter une 4e métrique : Heures sup. avec couleur accent si positif, neutre si 0

Form
- En haut : segmented control [ Bureau | Télétravail | Congé | Off ]
- Champs start/end/break affichés uniquement si type === WORKED (toggle JS Stimulus simple, ou rechargement Turbo Frame)

Monthly view templates/pages/month.html.twig
- Grille calendrier 7×6 (semaines complètes du mois)
- Chaque case : numéro du jour + type/heures, lien vers app_home?date=...
- Stats en haut : heures du mois, overtime cumulé, jours saisis/jours ouvrés attendus

Lien vers le mois dans la topbar ou via toggle dans le hero
                                                                                                                                                                                                                                                                                                                         
---                                                                                                                                                                                                                                                                                                                    
## Phase 6 — Profil

Page profil : ajouter une section "Préférences de saisie"
- workingDaysPerWeek
- defaultRemoteDays (checkboxes par jour de la semaine)
- Helper texte : "Les jours de télétravail prédéfinis seront pré-suggérés dans le formulaire d'accueil mais resteront modifiables."

  ---
### RAF

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
