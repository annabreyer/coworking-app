<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BookingFixtures;
use App\DataFixtures\BookingTypeFixtures;
use App\DataFixtures\PriceFixtures;
use App\Entity\BookingType;
use App\Entity\BusinessDay;
use App\Repository\BookingTypeRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;


class BaseTestController extends WebTestCase
{
    use ClockSensitiveTrait;

    protected function setupTestClient(
        string $mockDate,
        array $fixtures,
    ): KernelBrowser {
        static::mockTime(new \DateTimeImmutable($mockDate));
        $client = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures($fixtures);

        return $client;
    }

    protected function setupTestClientAndLogin(
        string $mockDate,
        array $fixtures,
        string $userEmail
    ): KernelBrowser {
        $client = $this->setupTestClient($mockDate, $fixtures);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneBy(['email' => $userEmail]);
        $client->loginUser($testUser);

        return $client;
    }

    protected function assertLogContains(string $message, string $level = 'critical'): void
    {
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);

        $testHandler = null;
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
                break;
            }
        }

        self::assertNotNull($testHandler);

        dump($testHandler->getRecords());

        self::assertTrue($testHandler->hasRecordThatContains(
            $message,
            Level::fromName($level)
        ));
    }

    protected function assertFlashMessageContains(KernelBrowser $client, string $message, string $type = 'error'): void
    {
        /** @var Session $session */
        $session = $client->getRequest()->getSession();
        $flashMessages = $session->getFlashBag()->get($type);
        self::assertContains($message, $flashMessages);
    }

    protected function assertFormValidationError(
        KernelBrowser $client,
        Form $form,
        array $formValues,
        string $expectedErrorMessage
    ): void {
        $form->setValues($formValues);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', $expectedErrorMessage);
    }

    protected function getBusinessDay(string $date): BusinessDay
    {
        return self::getRepository(BusinessDayRepository::class)
                     ->findOneBy(['date' => new \DateTimeImmutable($date)]);
    }

    protected function getBookingType(string $name): BookingType
    {
        return $this->getRepository(BookingTypeRepository::class)
                          ->findOneBy(['name' => $name]);
    }

    protected function submitFormAndAssertRedirect(
        KernelBrowser $client,
        Form $form,
        array $formValues,
        ?string $expectedRedirectUrl = null
    ): void {
        $form->setValues($formValues);
        $client->submit($form);

        if ($expectedRedirectUrl !== null) {
            static::assertResponseRedirects($expectedRedirectUrl);
        } else {
            static::assertResponseRedirects();
        }
    }

    protected function getRepository(string $repositoryServiceId): object
    {
        return static::getContainer()->get($repositoryServiceId);
    }


    protected function getEntity(string $repositoryServiceId, array $criteria): ?object
    {
        $repository = $this->getRepository($repositoryServiceId);
        return $repository->findOneBy($criteria);
    }
}