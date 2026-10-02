<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\User;
use App\Formatter\MoneyFormatter;
use App\Lease\DepositLimit;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Données saisies dans le formulaire de création d'un bail.
 */
final class LeaseData
{
    public const int MAX_TENANTS = 6;

    #[Assert\NotNull(message: 'Indiquez la date de début du bail.')]
    public ?\DateTimeImmutable $startDate = null;

    #[Assert\NotNull(message: 'Indiquez le jour de paiement.')]
    #[Assert\Range(notInRangeMessage: 'Choisissez un jour entre le 1er et le 28 du mois.', min: 1, max: 28)]
    public ?int $paymentDay = 1;

    #[Assert\NotNull(message: 'Indiquez le loyer hors charges.')]
    #[Assert\Positive(message: 'Le loyer doit être supérieur à 0.')]
    public ?int $rent = null;

    #[Assert\NotNull(message: 'Indiquez la provision sur charges (0 s\'il n\'y en a pas).')]
    #[Assert\PositiveOrZero(message: 'La provision sur charges ne peut pas être négative.')]
    public ?int $charges = null;

    #[Assert\NotNull(message: 'Indiquez le dépôt de garantie (0 s\'il n\'y en a pas).')]
    #[Assert\PositiveOrZero(message: 'Le dépôt de garantie ne peut pas être négatif.')]
    public ?int $deposit = null;

    #[Assert\NotNull(message: 'Choisissez le trimestre de référence.')]
    #[Assert\Range(min: 1, max: 4)]
    public ?int $irlReferenceQuarter = null;

    #[Assert\NotNull(message: 'Indiquez l\'année de l\'indice de référence.')]
    #[Assert\Range(notInRangeMessage: 'L\'indice de référence des loyers existe sous sa forme actuelle depuis 2008.', min: 2008, max: 2100)]
    public ?int $irlReferenceYear = null;

    /**
     * Les clés suivent celles du formulaire : après une suppression, elles peuvent avoir des trous (0, 2…).
     *
     * @var array<int, TenantData>
     */
    #[Assert\Valid]
    #[Assert\Count(
        min: 1,
        max: self::MAX_TENANTS,
        minMessage: 'Ajoutez au moins un locataire.',
        maxMessage: 'Un bail compte au plus {{ limit }} locataires.',
    )]
    public array $tenants = [];

    private function __construct(
        public readonly bool $furnished,
    ) {
    }

    /**
     * Un formulaire prêt à remplir : le type de bail suit le bien, avec un premier locataire à saisir.
     */
    public static function forProperty(Property $property): self
    {
        $data = new self($property->isFurnished());
        $data->tenants = [new TenantData()];

        return $data;
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (null !== $this->rent && null !== $this->deposit && $this->rent > 0
            && !DepositLimit::allows($this->deposit, $this->rent, $this->furnished)) {
            $context->buildViolation(\sprintf(
                'Le dépôt de garantie ne peut pas dépasser %s : %s de loyer hors charges pour une location %s.',
                MoneyFormatter::format(DepositLimit::maximumFor($this->rent, $this->furnished)),
                $this->furnished ? 'deux mois' : 'un mois',
                $this->furnished ? 'meublée' : 'vide',
            ))->atPath('deposit')->addViolation();
        }

        // L'indice de référence est le dernier publié à la signature : il ne peut pas être postérieur au début du bail
        if (null !== $this->startDate && null !== $this->irlReferenceYear
            && $this->irlReferenceYear > (int) $this->startDate->format('Y')) {
            $context->buildViolation('L\'indice de référence ne peut pas être postérieur au début du bail.')
                ->atPath('irlReferenceYear')
                ->addViolation();
        }

        $seenEmails = [];
        foreach ($this->tenants as $index => $tenant) {
            if (null === $tenant->email || '' === trim($tenant->email)) {
                continue;
            }

            $email = User::normalizeEmail($tenant->email);
            if (isset($seenEmails[$email])) {
                $context->buildViolation('Chaque locataire doit avoir sa propre adresse e-mail.')
                    ->atPath(\sprintf('tenants[%d].email', $index))
                    ->addViolation();
            }
            $seenEmails[$email] = true;
        }
    }

    /**
     * Les loyers sont suivis dès le début du bail, ou à partir du mois en cours pour un bail déjà commencé :
     * le propriétaire n'a pas à marquer comme payés des mois antérieurs à son arrivée dans l'application.
     */
    public function toLease(Property $property, \DateTimeImmutable $today): Lease
    {
        if (null === $this->startDate || null === $this->paymentDay || null === $this->rent || null === $this->charges
            || null === $this->deposit || null === $this->irlReferenceQuarter || null === $this->irlReferenceYear) {
            throw new \LogicException('Les données du bail doivent être validées avant d\'être enregistrées.');
        }

        $lease = new Lease(
            $property,
            $this->startDate,
            $this->rent,
            $this->charges,
            $this->deposit,
            $this->paymentDay,
            $this->irlReferenceQuarter,
            $this->irlReferenceYear,
            max(Lease::firstDayOfMonth($this->startDate), Lease::firstDayOfMonth($today)),
        );

        foreach ($this->tenants as $tenant) {
            if (null === $tenant->firstName || null === $tenant->lastName || null === $tenant->email) {
                throw new \LogicException('Les données des locataires doivent être validées avant d\'être enregistrées.');
            }
            $lease->addTenant($tenant->firstName, $tenant->lastName, $tenant->email);
        }

        return $lease;
    }
}
