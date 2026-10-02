<?php

declare(strict_types=1);

namespace App\Controller\Owner;

use App\Entity\Tenant;
use App\Security\Voter\PropertyVoter;
use App\Tenant\TenantInviter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TenantController extends AbstractController
{
    #[Route('/proprietaire/locataires/{id}/inviter', name: 'owner_tenant_invite', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    // Seul le propriétaire du bien loué peut inviter ses locataires
    #[IsGranted(PropertyVoter::EDIT, subject: new Expression('args["tenant"].getLease().getProperty()'))]
    #[IsCsrfTokenValid('invite-tenant', tokenKey: '_token')]
    public function invite(Tenant $tenant, TenantInviter $inviter): Response
    {
        $propertyUrl = $this->generateUrl('owner_property_show', ['id' => $tenant->getLease()->getProperty()->getId()]);

        if ($tenant->hasAccount()) {
            $this->addFlash('warning', \sprintf('%s a déjà accès à son espace locataire.', $tenant->getFullName()));

            return $this->redirect($propertyUrl, Response::HTTP_SEE_OTHER);
        }

        $inviter->invite($tenant);
        $this->addFlash('success', \sprintf('Invitation envoyée à %s. Le lien est valable 7 jours.', $tenant->getEmail()));

        return $this->redirect($propertyUrl, Response::HTTP_SEE_OTHER);
    }
}
