<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\EmailSubjectPrefixSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailSubjectPrefixSubscriberTest extends KernelTestCase
{
    public function testSentEmailIsPrefixedInTestEnvironment(): void
    {
        $mailer = static::getContainer()->get(MailerInterface::class);
        self::assertInstanceOf(MailerInterface::class, $mailer);
        $mailer->send($this->getEmail('Deine Buchung'));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, '[TEST] Deine Buchung');
    }

    public function testOnMessageDoesNotPrefixSubjectInProd(): void
    {
        $email = $this->getEmail('Deine Buchung');

        (new EmailSubjectPrefixSubscriber('prod'))->onMessage($this->getMessageEvent($email));

        self::assertSame('Deine Buchung', $email->getSubject());
    }

    /**
     * @dataProvider provideOnMessagePrefixesSubjectWithEnvironmentCases
     */
    public function testOnMessagePrefixesSubjectWithEnvironment(string $env, string $expectedSubject): void
    {
        $email = $this->getEmail('Deine Buchung');

        (new EmailSubjectPrefixSubscriber($env))->onMessage($this->getMessageEvent($email));

        self::assertSame($expectedSubject, $email->getSubject());
    }

    public function testOnMessageDoesNotPrefixSubjectTwice(): void
    {
        $email      = $this->getEmail('Deine Buchung');
        $subscriber = new EmailSubjectPrefixSubscriber('dev');

        $subscriber->onMessage($this->getMessageEvent($email));
        $subscriber->onMessage($this->getMessageEvent($email));

        self::assertSame('[DEV] Deine Buchung', $email->getSubject());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideOnMessagePrefixesSubjectWithEnvironmentCases(): iterable
    {
        return [
            'dev'     => ['dev', '[DEV] Deine Buchung'],
            'staging' => ['staging', '[STAGING] Deine Buchung'],
            'test'    => ['test', '[TEST] Deine Buchung'],
        ];
    }

    private function getEmail(string $subject): Email
    {
        return (new Email())
            ->from('office@coworking-hahnheim.de')
            ->to('user.one@annabreyer.dev')
            ->subject($subject)
            ->text('text')
        ;
    }

    private function getMessageEvent(Email $email): MessageEvent
    {
        return new MessageEvent(
            $email,
            new Envelope(new Address('office@coworking-hahnheim.de'), [new Address('user.one@annabreyer.dev')]),
            'null://null'
        );
    }
}
