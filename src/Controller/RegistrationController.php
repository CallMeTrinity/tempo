<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\Roles;
use App\Form\RegistrationFormType;
use App\Repository\BlacklistedEmailRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
    ) {
    }

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
            // isEmailVerified passe à true quand l'utilisateur clique sur le lien
            // de confirmation reçu par mail (cf. verifyUserEmail).
            $user->setIsEmailVerified(false);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->to((string) $user->getEmail())
                    ->subject('Confirmez votre adresse email · Tempo')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            $security->login($user);

            $this->addFlash('info', 'Compte créé. Vérifiez votre boîte mail pour confirmer votre adresse. Un administrateur validera ensuite votre compte.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, Security $security): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        // Le lien de confirmation est signé pour un user précis : il doit être
        // connecté pour qu'on puisse valider la signature contre son id/email.
        if (!$user instanceof User) {
            $this->addFlash('verify_email_error', 'Connectez-vous puis recliquez sur le lien de confirmation.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());

            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('success', 'Votre adresse email a bien été confirmée.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resendVerificationEmail(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('resend_verify_email', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        /** @var User|null $user */
        $user = $this->getUser();

        if ($user instanceof User && !$user->isEmailVerified()) {
            // Délai anti-spam : on refuse le renvoi tant que le cooldown court.
            if ($user->getVerificationEmailCooldownRemaining() > 0) {
                $this->addFlash('warning', 'Un email a déjà été envoyé récemment. Patientez avant d\'en redemander un.');

                return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
            }

            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->to((string) $user->getEmail())
                    ->subject('Confirmez votre adresse email · Tempo')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );
            $this->addFlash('info', 'Un nouvel email de confirmation vient de vous être envoyé.');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_home'));
    }
}
