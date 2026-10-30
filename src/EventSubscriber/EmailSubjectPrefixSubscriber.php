<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

class EmailSubjectPrefixSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $env)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => 'onMessage',
        ];
    }

    public function onMessage(MessageEvent $event): void
    {
        if ('prod' === $this->env) {
            return;
        }

        $message = $event->getMessage();
        if (false === $message instanceof Email) {
            return;
        }

        $prefix = '[' . strtoupper($this->env) . '] ';
        if (str_starts_with((string) $message->getSubject(), $prefix)) {
            return;
        }

        $message->subject($prefix . $message->getSubject());
    }
}
