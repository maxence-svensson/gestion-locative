<?php

declare(strict_types=1);

namespace App\Controller\Owner;

use App\Entity\User;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/proprietaire', name: 'owner_dashboard', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user, PropertyRepository $properties): Response
    {
        return $this->render('owner/dashboard.html.twig', [
            'property_count' => $properties->countByOwner($user),
        ]);
    }
}
