<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Invitation d'un locataire dans son espace : le propriétaire l'envoie, le locataire l'accepte
 * en créant son mot de passe (ou en la rattachant au compte qu'il a déjà).
 */
final class TenantInviter
{
    public const string VALIDITY = '+7 days';

    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function invite(Tenant $tenant): void
    {
        // 32 octets aléatoires : impossible à deviner, même en essayant des milliards de liens
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = $this->clock->now();
        $expiresAt = $now->modify(self::VALIDITY);

        $tenant->invite(self::hash($token), $now, $expiresAt);
        $this->entityManager->flush();

        $lease = $tenant->getLease();
        $owner = $lease->getProperty()->getOwner();

        // Envoyé en arrière-plan par le worker (Messenger) : la page du propriétaire n'attend pas le serveur d'e-mails
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($tenant->getEmail(), $tenant->getFullName()))
            ->replyTo(new Address($owner->getEmail(), $owner->getFullName()))
            ->subject(\sprintf('%s vous invite dans votre espace locataire', $owner->getFullName()))
            ->htmlTemplate('emails/tenant_invitation.html.twig')
            ->textTemplate('emails/tenant_invitation.txt.twig')
            ->context([
                'tenant' => $tenant,
                'owner' => $owner,
                'property' => $lease->getProperty(),
                'invitation_url' => $this->urlGenerator->generate('app_invitation', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
                'expires_at' => $expiresAt,
            ]));
    }

    public function findPendingInvitation(string $token): ?Tenant
    {
        return $this->tenants->findOneByValidInvitation(self::hash($token), $this->clock->now());
    }

    /**
     * Rattache le compte au locataire et lui donne accès à l'espace locataire.
     */
    public function accept(Tenant $tenant, User $user): void
    {
        $user->grantRole(User::ROLE_TENANT);
        $tenant->attachUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
