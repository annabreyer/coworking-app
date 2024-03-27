<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserAction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class UserActionSubscriber implements EventSubscriberInterface
{
    /**
     * @var string[]
     */
    private array $subscribedMethods;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
        $this->subscribedMethods = [
            Request::METHOD_POST,
            Request::METHOD_PUT,
            Request::METHOD_PATCH,
            Request::METHOD_DELETE,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (false === \in_array($request->getMethod(), $this->subscribedMethods, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (null === $user || false === $user instanceof User) {
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $this->saveUserRequest($user, $request);
    }

    private function saveUserRequest(User $user, Request $request): void
    {
        $data  = $request->request->all();
        $match = preg_grep('/form/', array_keys($data));

        if (false !== $match && false === empty($match)) {
            $formName = $match[0];
            $data     = $data[$formName];
        }

        $action = new UserAction();
        $action->setUser($user);
        $action->setRequestUri($request->getRequestUri());
        $action->setData($data);

        if (isset($formName)) {
            $action->setForm($formName);
        }

        $this->entityManager->persist($action);
        $this->entityManager->flush();
    }

    public function saveAdminRequest(User $user, Request $request): void
    {
        //        $match = preg_grep('/ea/', array_keys($data));
        //        if (false === empty($match)) {
        //            $formName = $match[0];
        //        }
    }
}
