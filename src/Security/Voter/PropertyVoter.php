<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Seul le propriétaire d'un bien peut le consulter, le modifier ou le supprimer.
 *
 * @extends Voter<string, Property>
 */
final class PropertyVoter extends Voter
{
    public const string VIEW = 'PROPERTY_VIEW';
    public const string EDIT = 'PROPERTY_EDIT';
    public const string DELETE = 'PROPERTY_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Property;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Les trois actions suivent la même règle pour l'instant. Plus tard, le locataire
        // pourra consulter (VIEW) le logement qu'il loue, sans pouvoir le modifier.
        return $subject->isOwnedBy($user);
    }
}
