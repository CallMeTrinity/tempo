<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\Roles;
use App\Form\RegistrationFormType;
use App\Repository\BlacklistedEmailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        BlacklistedEmailRepository $blacklist,
        Security $security,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Blacklist check : un email refusé par un admin ne peut pas
            // recréer de compte.
            if ($blacklist->existsByEmail((string) $user->getEmail())) {
                $this->addFlash('error', 'Cette adresse email ne peut pas être utilisée pour créer un compte. Contactez un administrateur.');

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $firstName = substr($user->getEmail(), 0, strpos($user->getEmail(), '.'));
            $user->setFirstName(ucfirst($firstName));

            $username = substr($user->getEmail(), 0, strpos($user->getEmail(), '@'));
            $user->setUsername($username);

            $user->setRole(Roles::USER);
            // isVerified représente la validation admin. Tant qu'un admin n'a
            // pas approuvé le compte, isVerified reste à false (mais l'utilisateur
            // peut quand même se connecter et utiliser la plateforme).
            $user->setIsVerified(false);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            $security->login($user);

            $this->addFlash('info', 'Compte créé. Un administrateur validera votre compte sous peu.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
