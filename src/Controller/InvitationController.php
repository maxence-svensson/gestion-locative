<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountActivationFormType;
use App\Form\Data\AccountActivationData;
use App\Repository\UserRepository;
use App\Tenant\TenantInviter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page ouverte depuis l'e-mail d'invitation, accessible sans être connecté : le lien fait office de preuve.
 *
 * Rien ne change à la simple ouverture du lien (GET) : certaines messageries ouvrent les liens
 * automatiquement pour les analyser. Il faut envoyer le formulaire (POST) pour accepter.
 */
final class InvitationController extends AbstractController
{
    #[Route('/invitation/{token}', name: 'app_invitation', requirements: ['token' => '[A-Za-z0-9_-]{43}'], methods: ['GET', 'POST'])]
    public function __invoke(
        string $token,
        Request $request,
        TenantInviter $inviter,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        $tenant = $inviter->findPendingInvitation($token);

        if (null === $tenant) {
            return $this->render('invitation/invalid.html.twig', [], new Response(status: Response::HTTP_NOT_FOUND));
        }

        $existingAccount = $users->findOneBy(['email' => $tenant->getEmail()]);

        // Le locataire a déjà un compte (il est par exemple aussi propriétaire) : la location y est ajoutée
        if (null !== $existingAccount) {
            $form = $this->createFormBuilder()->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // À lire avant d'ajouter le rôle locataire : Symfony déconnecte par sécurité un utilisateur
                // dont les rôles ont changé depuis l'ouverture de sa session
                $currentUser = $this->getUser();
                $inviter->accept($tenant, $existingAccount);

                if ($currentUser instanceof User && $currentUser->getId() === $existingAccount->getId()) {
                    // Nouvelle connexion : la session tient compte du rôle locataire qui vient d'être ajouté
                    $security->login($existingAccount, 'form_login', 'main');
                    $this->addFlash('success', 'Votre location a été ajoutée à votre compte.');

                    return $this->redirectToRoute('tenant_dashboard', status: Response::HTTP_SEE_OTHER);
                }

                $this->addFlash('success', 'Votre location a été ajoutée à votre compte. Connectez-vous pour y accéder.');

                return $this->redirectToRoute('app_login', status: Response::HTTP_SEE_OTHER);
            }

            return $this->render('invitation/accept.html.twig', [
                'tenant' => $tenant,
                'form' => $form,
                'has_account' => true,
            ]);
        }

        $data = new AccountActivationData();
        $form = $this->createForm(AccountActivationFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $data->plainPassword);

            $user = new User($tenant->getEmail(), $tenant->getFirstName(), $tenant->getLastName());
            $user->setPassword($passwordHasher->hashPassword($user, $data->plainPassword));
            $inviter->accept($tenant, $user);

            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Bienvenue ! Votre espace locataire est prêt.');

            return $this->redirectToRoute('tenant_dashboard', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('invitation/accept.html.twig', [
            'tenant' => $tenant,
            'form' => $form,
            'has_account' => false,
        ]);
    }
}
