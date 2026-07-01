<?php

namespace App\Controller;

use App\Entity\User;
use App\Export\ExportFormat;
use App\Export\TimeEntryExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class ExportController extends AbstractController
{
    /**
     * Export des données de pointage de l'utilisateur courant.
     * Période : mois courant par défaut, ou plage `from`/`to` libre.
     */
    #[Route('/export', name: 'app_export', methods: ['GET'])]
    public function export(Request $request, TimeEntryExporter $exporter): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $today = new \DateTimeImmutable('today');
        $from = $this->parseDate($request->query->get('from'))
            ?? $today->modify('first day of this month');
        $to = $this->parseDate($request->query->get('to'))
            ?? $today->modify('last day of this month');

        // Plage inversée : on remet dans l'ordre plutôt que d'échouer.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $format = ExportFormat::fromRequest($request->query->get('format'));

        return $exporter->export($user, $from, $to, $format);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
