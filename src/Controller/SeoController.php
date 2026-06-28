<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $body = <<<TXT
            User-agent: *
            Allow: /
            Disallow: /admin
            Disallow: /login
            Disallow: /profile

            Sitemap: {$sitemap}
            TXT;

        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        // Only public, indexable pages belong in the sitemap.
        $urls = [
            ['route' => 'app_landing', 'changefreq' => 'monthly', 'priority' => '1.0'],
            ['route' => 'app_register', 'changefreq' => 'yearly', 'priority' => '0.5'],
        ];

        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
