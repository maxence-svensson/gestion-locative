<?php

declare(strict_types=1);

namespace App\Controller\Owner;

use App\Entity\Lease;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Repository\PropertyRepository;
use App\Repository\RentDueRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/proprietaire', name: 'owner_dashboard', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] User $user,
        PropertyRepository $properties,
        RentDueRepository $dues,
        PaymentRepository $payments,
        ClockInterface $clock,
    ): Response {
        $today = $clock->now();
        $monthStart = Lease::firstDayOfMonth($today);

        return $this->render('owner/dashboard.html.twig', [
            'property_count' => $properties->countByOwner($user),
            'current_month' => $today,
            'due_this_month' => $dues->sumForOwnerAndPeriod($user, $monthStart),
            'received_this_month' => $payments->sumReceivedByOwner($user, $monthStart, $monthStart->modify('last day of this month')),
            'overdue_count' => $dues->countOverdueForOwner($user, $today),
        ]);
    }
}
