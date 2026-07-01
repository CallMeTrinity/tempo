<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Un compte ADMIN n'est pas un employé : il ne saisit pas ses heures et n'a
 * pas accès aux pages destinées aux utilisateurs (semaine, mois, profil,
 * planification). Toute requête de ce type est silencieusement redirigée
 * vers l'espace administration.
 */
final class AdminRedirectListener implements EventSubscriberInterface
{
    private const ADMIN_LANDING = 'app_admin_index';

    /**
     * Noms (ou préfixes) de routes interdits aux admins.
     */
    private const USER_ROUTES = [
        'app_home',
        'app_month',
        'app_month_current',
        'app_profile_',
        'app_time_entry_',
        'app_week_',
        'app_planning_',
        'app_export',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité basse (< 8) pour tourner après le RouterListener (priorité 32)
        // qui peuple `_route`, et après le FirewallListener (priorité 8) qui
        // authentifie l'utilisateur. Sinon `_route` serait vide et getUser()
        // renverrait null → aucun redirect ne se ferait.
        return [KernelEvents::REQUEST => ['onRequest', 4]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isAdmin()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route');
        if ($route === '' || !$this->isUserRoute($route)) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->router->generate(self::ADMIN_LANDING),
        ));
    }

    private function isUserRoute(string $route): bool
    {
        foreach (self::USER_ROUTES as $prefix) {
            if ($route === $prefix || str_starts_with($route, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
