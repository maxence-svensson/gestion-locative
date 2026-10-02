<?php

declare(strict_types=1);

namespace App\Controller\Tenant;

use App\Entity\User;
use App\Repository\LeaseRepository;
use App\Repository\RentDueRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/locataire', name: 'tenant_dashboard', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] User $user,
        LeaseRepository $leases,
        RentDueRepository $dues,
        ClockInterface $clock,
    ): Response {
        // Un locataire loue en général un seul logement : une requête par bail reste raisonnable
        $rentals = array_map(
            static fn ($lease): array => ['lease' => $lease, 'dues' => $dues->findLatestOf($lease)],
            $leases->findForTenantAccount($user),
        );

        return $this->render('tenant/dashboard.html.twig', [
            'rentals' => $rentals,
            'today' => $clock->now(),
        ]);
    }
}
