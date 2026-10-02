<?php

declare(strict_types=1);

namespace App\Controller\Owner;

use App\Entity\RentDue;
use App\Enum\ReceiptType;
use App\Form\Data\PaymentData;
use App\Form\PaymentFormType;
use App\Payment\PaymentExceedsRemainingAmount;
use App\Payment\PaymentRecorder;
use App\Payment\ReceiptGenerator;
use App\Security\Voter\RentDueVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/proprietaire/echeances/{id}', requirements: ['id' => Requirement::DIGITS])]
final class RentDueController extends AbstractController
{
    #[Route('/paiement', name: 'owner_rent_due_payment', methods: ['GET', 'POST'])]
    #[IsGranted(RentDueVoter::RECORD_PAYMENT, 'due')]
    public function recordPayment(Request $request, RentDue $due, PaymentRecorder $recorder, ClockInterface $clock): Response
    {
        $propertyUrl = $this->generateUrl('owner_property_show', ['id' => $due->getLease()->getProperty()->getId()]);

        if ($due->isPaid()) {
            $this->addFlash('warning', 'Cette échéance est déjà entièrement payée.');

            return $this->redirect($propertyUrl, Response::HTTP_SEE_OTHER);
        }

        $data = PaymentData::forDue($due, $clock->now());
        $form = $this->createForm(PaymentFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $data->amount && null !== $data->paidOn && null !== $data->method);

            try {
                $recorder->record($due, $data->amount, $data->paidOn, $data->method);

                $this->addFlash('success', ReceiptType::Quittance === $due->getReceiptType()
                    ? 'Le paiement a été enregistré. La quittance est disponible.'
                    : 'Le paiement partiel a été enregistré. Un reçu est disponible.');

                return $this->redirect($propertyUrl, Response::HTTP_SEE_OTHER);
            } catch (PaymentExceedsRemainingAmount $exception) {
                // Un autre paiement a été enregistré entre l'affichage du formulaire et son envoi
                $form->get('amount')->addError(new FormError($exception->getMessage()));
            }
        }

        // Formulaire invalide (y compris l'erreur ajoutée ci-dessus) : statut 422, comme l'attend Turbo
        return $this->render('owner/rent_due/payment.html.twig', [
            'due' => $due,
            'form' => $form,
        ]);
    }

    #[Route('/justificatif', name: 'owner_rent_due_receipt', methods: ['GET'])]
    #[IsGranted(RentDueVoter::VIEW, 'due')]
    public function downloadReceipt(RentDue $due, ReceiptGenerator $receipts): Response
    {
        if (null === $due->getReceiptType()) {
            throw $this->createNotFoundException('Aucun paiement n\'a encore été reçu pour cette échéance.');
        }

        return $receipts->generate($due)->toResponse();
    }
}
