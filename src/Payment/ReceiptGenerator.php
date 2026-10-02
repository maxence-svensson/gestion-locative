<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\RentDue;
use App\Pdf\PdfRenderer;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

/**
 * Quittance (loyer payé en entier) ou reçu (paiement partiel) d'une échéance, en PDF.
 */
final class ReceiptGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly PdfRenderer $pdfRenderer,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @throws \LogicException si l'échéance n'a encore reçu aucun paiement
     */
    public function generate(RentDue $due): GeneratedReceipt
    {
        $type = $due->getReceiptType() ?? throw new \LogicException('Aucun paiement n\'a été reçu pour cette échéance.');

        $html = $this->twig->render('documents/receipt.html.twig', [
            'due' => $due,
            'type' => $type,
            'lease' => $due->getLease(),
            'property' => $due->getLease()->getProperty(),
            'owner' => $due->getLease()->getProperty()->getOwner(),
        ]);

        // Par exemple « quittance-2026-10-t2-croix-rousse.pdf »
        $filename = \sprintf(
            '%s-%s-%s.pdf',
            $type->value,
            $due->getPeriod()->format('Y-m'),
            $this->slugger->slug($due->getLease()->getProperty()->getName())->lower(),
        );

        return new GeneratedReceipt($filename, $this->pdfRenderer->render($html));
    }
}
