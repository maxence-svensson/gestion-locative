<?php

declare(strict_types=1);

namespace App\Controller\Owner;

use App\Entity\Property;
use App\Entity\User;
use App\Form\Data\PropertyData;
use App\Form\PropertyFormType;
use App\Repository\LeaseRepository;
use App\Repository\PropertyRepository;
use App\Repository\RentDueRepository;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/proprietaire/biens', name: 'owner_property_')]
final class PropertyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LeaseRepository $leases,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, PropertyRepository $properties): Response
    {
        return $this->render('owner/property/index.html.twig', [
            'properties' => $properties->findByOwner($user),
            'leased_property_ids' => $this->leases->findLeasedPropertyIds($user),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $data = new PropertyData();
        $form = $this->createForm(PropertyFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property = $data->toProperty($user);
            $this->entityManager->persist($property);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le bien a été ajouté.');

            return $this->redirectToRoute('owner_property_show', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        // Un formulaire invalide est renvoyé avec un statut 422, ce qu'attend Turbo pour l'afficher
        return $this->render('owner/property/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(PropertyVoter::VIEW, 'property')]
    public function show(Property $property, RentDueRepository $dues, ClockInterface $clock): Response
    {
        $lease = $this->leases->findOneByProperty($property);

        return $this->render('owner/property/show.html.twig', [
            'property' => $property,
            'lease' => $lease,
            'dues' => null !== $lease ? $dues->findLatestOf($lease) : [],
            'today' => $clock->now(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, 'property')]
    public function edit(Request $request, Property $property): Response
    {
        $data = PropertyData::fromProperty($property);
        $form = $this->createForm(PropertyFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data->applyTo($property);
            $this->entityManager->flush();

            $this->addFlash('success', 'Les modifications ont été enregistrées.');

            return $this->redirectToRoute('owner_property_show', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('owner/property/edit.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(PropertyVoter::DELETE, 'property')]
    #[IsCsrfTokenValid('delete-property', tokenKey: '_token')]
    public function delete(Property $property): Response
    {
        // Un bien loué reste lié à son bail (et bientôt à ses loyers et quittances) : on ne le supprime pas
        if (null !== $this->leases->findOneByProperty($property)) {
            $this->addFlash('warning', 'Ce bien est loué : il ne peut pas être supprimé.');

            return $this->redirectToRoute('owner_property_show', ['id' => $property->getId()], Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->remove($property);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le bien a été supprimé.');

        return $this->redirectToRoute('owner_property_index', status: Response::HTTP_SEE_OTHER);
    }
}
