<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'arrivée après la connexion : envoie chaque utilisateur vers son espace.
 * Un utilisateur peut être à la fois propriétaire et locataire ; l'espace propriétaire est alors prioritaire.
 */
final class DashboardController extends AbstractController
{
    #[Route('/espace', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        if ($this->isGranted(User::ROLE_OWNER)) {
            return $this->redirectToRoute('owner_dashboard');
        }

        if ($this->isGranted(User::ROLE_TENANT)) {
            return $this->redirectToRoute('tenant_dashboard');
        }

        throw $this->createAccessDeniedException('Aucun espace n\'est associé à ce compte.');
    }
}
