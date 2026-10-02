<?php

declare(strict_types=1);

namespace App\Controller\Tenant;

use App\Entity\RentDue;
use App\Payment\ReceiptGenerator;
use App\Security\Voter\RentDueVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RentDueController extends AbstractController
{
    #[Route('/locataire/echeances/{id}/justificatif', name: 'tenant_rent_due_receipt', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(RentDueVoter::VIEW, 'due')]
    public function downloadReceipt(RentDue $due, ReceiptGenerator $receipts): Response
    {
        if (null === $due->getReceiptType()) {
            throw $this->createNotFoundException('Aucun paiement n\'a encore été reçu pour cette échéance.');
        }

        return $receipts->generate($due)->toResponse();
    }
}
