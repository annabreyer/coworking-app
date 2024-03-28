<?php declare(strict_types = 1);

namespace App\EventSubscriber;

use App\Entity\AdminAction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\SerializerInterface;

class AdminActionSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SerializerInterface $serializer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityUpdatedEvent::class => 'saveAdminAction',
        ];
    }

    public function saveAdminAction(AfterEntityUpdatedEvent $event):void
    {
        $user = $event->getEntityInstance();
        if (false === $user Instanceof User) {
            return;
        }

        $adminUser = $this->security->getUser();
        if (false === $adminUser Instanceof User) {
            throw new \LogicException('Currently logged in user is not an instance of User?!');
        }

        $normalizedUser = $this->serializer->normalize($user, 'json');

        $adminAction = new AdminAction();
        $adminAction
            ->setAdminUser($adminUser)
            ->setUser($user)
            ->setData($normalizedUser);

        $this->entityManager->persist($adminAction);
        $this->entityManager->flush();
    }

}