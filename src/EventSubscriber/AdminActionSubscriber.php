<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AdminAction;
use App\Entity\Booking;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AdminActionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private NormalizerInterface $normalizer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityUpdatedEvent::class => 'saveAdminAction',
        ];
    }

    public function saveAdminAction(AfterEntityUpdatedEvent $event): void
    {
        $adminUser = $this->security->getUser();
        if (false === $adminUser instanceof User) {
            throw new \LogicException('Currently logged in user is not an instance of User?!');
        }

        $entityInstance = $event->getEntityInstance();
        if ($entityInstance instanceof User) {
            $this->saveAdminUserAction($entityInstance, $adminUser);

            return;
        }

        if ($entityInstance instanceof Booking) {
            $this->saveAdminBookingAction($entityInstance, $adminUser);

            return;
        }
    }

    private function saveAdminUserAction(User $user, User $adminUser): void
    {
        $normalizedUser = $this->normalizer->normalize($user, null, ['groups' => 'admin_action_user']);
        if (false === \is_array($normalizedUser)) {
            throw new \LogicException('Normalized user is not an array?!');
        }

        $adminAction = new AdminAction();
        $adminAction
            ->setAdminUser($adminUser)
            ->setUser($user)
            ->setData($normalizedUser);

        $this->entityManager->persist($adminAction);
        $this->entityManager->flush();
    }

    private function saveAdminBookingAction(Booking $booking, User $adminUser): void
    {
        if (null === $booking->getUser()) {
            throw new \LogicException('Booking does not have a user?!');
        }

        $normalizedBooking = $this->normalizer->normalize($booking, null, ['groups' => 'admin_action_booking']);
        if (false === \is_array($normalizedBooking)) {
            throw new \LogicException('Normalized booking is not an array?!');
        }

        $adminAction = new AdminAction();
        $adminAction
            ->setAdminUser($adminUser)
            ->setUser($booking->getUser())
            ->setData($normalizedBooking);

        $this->entityManager->persist($adminAction);
        $this->entityManager->flush();
    }
}
