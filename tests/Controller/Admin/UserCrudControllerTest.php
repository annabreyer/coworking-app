<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\UserCrudController;
use App\DataFixtures\BasicFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Tests\Controller\BaseTestController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class UserCrudControllerTest extends BaseTestController
{
    private const ADMIN_EMAIL = 'admin@annabreyer.dev';
    private const USER_EMAIL  = 'user.one@annabreyer.dev';

    public function testAdminCanEditBillingAddress(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BasicFixtures::class], self::ADMIN_EMAIL);
        $user   = $this->getUser();

        $crawler = $client->request('GET', $this->getAdminUrl('edit', $user->getId()));
        static::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="User"]')->form();

        $form['User[billingAddress_name]']     = 'ACME GmbH';
        $form['User[billingAddress_street]']   = 'Industriestraße 5';
        $form['User[billingAddress_postCode]'] = '55128';
        $form['User[billingAddress_city]']     = 'Mainz';
        $client->submit($form);

        static::assertResponseRedirects();

        $billingAddress = $this->getUser()->getBillingAddress();
        self::assertSame('ACME GmbH', $billingAddress->getName());
        self::assertSame('Industriestraße 5', $billingAddress->getStreet());
        self::assertSame('55128', $billingAddress->getPostCode());
        self::assertSame('Mainz', $billingAddress->getCity());
    }

    public function testDetailPageShowsBillingAddress(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BasicFixtures::class], self::ADMIN_EMAIL);
        $user   = $this->getUser();
        $user->getBillingAddress()
             ->setName('ACME GmbH')
             ->setStreet('Industriestraße 5')
             ->setPostCode('55128')
             ->setCity('Mainz')
        ;
        $this->getEntityManager()->flush();

        $client->request('GET', $this->getAdminUrl('detail', $user->getId()));

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('body', 'Billing Address');
        static::assertSelectorTextContains('body', 'ACME GmbH');
    }

    private function getAdminUrl(string $crudAction, ?int $entityId): string
    {
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        self::assertInstanceOf(AdminUrlGenerator::class, $adminUrlGenerator);

        return $adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction($crudAction)
            ->setEntityId($entityId)
            ->generateUrl()
        ;
    }

    private function getUser(): User
    {
        $this->getEntityManager()->clear();

        $userRepository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $userRepository);
        $user = $userRepository->findOneBy(['email' => self::USER_EMAIL]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
