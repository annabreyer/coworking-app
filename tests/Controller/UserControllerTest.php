<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\Test\UserAndAdminFixture;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class UserControllerTest extends BaseTestController
{
    public function testEditUserShowsBillingAddressFields(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [UserAndAdminFixture::class], UserAndAdminFixture::USER_EMAIL);

        $client->request('GET', '/user/edit');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('body', 'Abweichende Rechnungsadresse');
        static::assertSelectorExists('input[name="user_data_form[billingAddress][name]"]');
        static::assertSelectorExists('input[name="user_data_form[billingAddress][street]"]');
        static::assertSelectorExists('input[name="user_data_form[billingAddress][postCode]"]');
        static::assertSelectorExists('input[name="user_data_form[billingAddress][city]"]');
    }

    public function testEditUserSavesBillingAddress(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [UserAndAdminFixture::class], UserAndAdminFixture::USER_EMAIL);

        $this->submitUserDataForm($client, [
            'user_data_form[billingAddress][name]'     => 'ACME GmbH',
            'user_data_form[billingAddress][street]'   => 'Hauptstraße 1',
            'user_data_form[billingAddress][postCode]' => '55262',
            'user_data_form[billingAddress][city]'     => 'Hahnheim',
        ]);

        static::assertResponseRedirects('/user');

        $billingAddress = $this->getTestUser()->getBillingAddress();
        self::assertSame('ACME GmbH', $billingAddress->getName());
        self::assertSame('Hauptstraße 1', $billingAddress->getStreet());
        self::assertSame('55262', $billingAddress->getPostCode());
        self::assertSame('Hahnheim', $billingAddress->getCity());
    }

    public function testEditUserWithoutBillingAddressKeepsItEmpty(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [UserAndAdminFixture::class], UserAndAdminFixture::USER_EMAIL);

        $this->submitUserDataForm($client, []);

        static::assertResponseRedirects('/user');
        self::assertTrue($this->getTestUser()->getBillingAddress()->isEmpty());
    }

    public function testEditUserRejectsIncompleteBillingAddress(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [UserAndAdminFixture::class], UserAndAdminFixture::USER_EMAIL);

        $this->submitUserDataForm($client, [
            'user_data_form[billingAddress][name]' => 'ACME GmbH',
        ]);

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('body', 'Bitte fülle alle Felder der Rechnungsadresse aus oder lass sie alle leer.');
        self::assertTrue($this->getTestUser()->getBillingAddress()->isEmpty());
    }

    /**
     * @param array<string, string> $billingAddressValues
     */
    private function submitUserDataForm(KernelBrowser $client, array $billingAddressValues): void
    {
        $crawler = $client->request('GET', '/user/edit');
        $form    = $crawler->selectButton('Speichern')->form();

        $form['user_data_form[firstName]']   = 'Anna';
        $form['user_data_form[lastName]']    = 'Muster';
        $form['user_data_form[mobilePhone]'] = '01701234567';
        $form['user_data_form[street]']      = 'Dorfstraße 2';
        $form['user_data_form[postCode]']    = '55262';
        $form['user_data_form[city]']        = 'Hahnheim';

        foreach ($billingAddressValues as $field => $value) {
            $form[$field] = $value;
        }

        $client->submit($form);
    }

    private function getTestUser(): User
    {
        $this->getEntityManager()->clear();

        $userRepository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $userRepository);
        $user = $userRepository->findOneBy(['email' => UserAndAdminFixture::USER_EMAIL]);
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
