<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\RentDue;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Seul le propriétaire du bien loué peut consulter une échéance, y enregistrer un paiement
 * et télécharger sa quittance ou son reçu.
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

        // Plus tard, le locataire pourra consulter (VIEW) ses échéances et télécharger ses quittances
        return $subject->getLease()->getProperty()->isOwnedBy($user);
    }
}
