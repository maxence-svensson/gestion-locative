<?php

declare(strict_types=1);

namespace App\Controller\Owner;

use App\Entity\Property;
use App\Form\Data\LeaseData;
use App\Form\LeaseFormType;
use App\Lease\RentDueGenerator;
use App\Repository\LeaseRepository;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LeaseController extends AbstractController
{
    #[Route('/proprietaire/biens/{id}/bail/nouveau', name: 'owner_lease_new', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, 'property')]
    public function new(
        Request $request,
        Property $property,
        LeaseRepository $leases,
        EntityManagerInterface $entityManager,
        RentDueGenerator $rentDueGenerator,
        ClockInterface $clock,
    ): Response {
        if (null !== $leases->findOneByProperty($property)) {
            $this->addFlash('warning', 'Ce bien a déjà un bail en cours.');

            return $this->redirectToRoute('owner_property_show', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        $data = LeaseData::forProperty($property);
        $form = $this->createForm(LeaseFormType::class, $data, ['furnished' => $property->isFurnished()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lease = $data->toLease($property, $clock->now());
            $entityManager->persist($lease);
            $entityManager->flush();

            // Les échéances jusqu'au mois en cours apparaissent tout de suite, sans attendre la tâche quotidienne
            $rentDueGenerator->generateFor($lease, $clock->now());

            $this->addFlash('success', 'Le bail a été créé.');

            return $this->redirectToRoute('owner_property_show', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('owner/lease/new.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }
}
