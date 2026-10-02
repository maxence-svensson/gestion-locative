<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\RentDue;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Le propriétaire du bien loué peut consulter une échéance, y enregistrer un paiement et télécharger
 * sa quittance ou son reçu. Les locataires du bail peuvent seulement la consulter et télécharger ses justificatifs.
 *
 * @extends Voter<string, RentDue>
 */
final class RentDueVoter extends Voter
{
    public const string VIEW = 'RENT_DUE_VIEW';
    public const string RECORD_PAYMENT = 'RENT_DUE_RECORD_PAYMENT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::RECORD_PAYMENT], true)
            && $subject instanceof RentDue;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $lease = $subject->getLease();

        return match ($attribute) {
            self::VIEW => $lease->getProperty()->isOwnedBy($user) || $lease->hasTenantAccount($user),
            default => $lease->getProperty()->isOwnedBy($user),
        };
    }
}
