<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('landing/index.html.twig');
    }
}
