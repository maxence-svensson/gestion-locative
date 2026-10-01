<?php

declare(strict_types=1);

namespace App\Controller\Tenant;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/locataire', name: 'tenant_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('tenant/dashboard.html.twig');
    }
}
