<?php

declare(strict_types=1);

namespace App\Controller\Owner;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/proprietaire', name: 'owner_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('owner/dashboard.html.twig');
    }
}
