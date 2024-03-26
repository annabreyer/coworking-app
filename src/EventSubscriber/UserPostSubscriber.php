<?php declare(strict_types = 1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserAction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserPostSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $entityManager, private TokenStorageInterface $tokenStorage)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            RequestEvent::class => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if ('POST' !== $request->getMethod()) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (null === $token || null === $token->getUser()) {
            return;
        }

        $user = $token->getUser();

        if (false === $user instanceof User) {
            return;
        }

        $this->saveFormSubmit($user, $request);
    }

    private function saveFormSubmit(User $user, Request $request): void
    {
        $data = $request->request->all();
        $match = preg_grep('/form/', array_keys($data));

        if (false === empty($match)) {
            $formName = $match[0];
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
}