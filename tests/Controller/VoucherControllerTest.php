<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\PriceFixtures;
use App\Repository\InvoiceRepository;
use App\Repository\PriceRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class VoucherControllerTest extends WebTestCase
{
    protected ?AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testIndexTemplateContainsFormWithVoucherTypeAndPaymentMethod(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);

        $client->request('GET', '/voucher');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('select[name="voucherPrice"]');
        $this->assertSelectorExists('input[name="paymentMethod"]');
    }

    public function testIndexFormSubmitWithInvalidToken(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form->setValues(['token' => 'invalid']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid CSRF Token.');
    }

    public function testIndexFormSubmitWithMissingVoucherPriceId(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        unset($form['voucherPrice']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Please select a voucher.');
    }

    public function testIndexFormSubmitWithInvalidVoucherPriceId(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form->setValues(['voucherPrice' => 999999]);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Selected voucher not found in the database.');
    }

    public function testIndexFormSubmitWithMissingPaymentMethod(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);

        $voucherPrice = static::getContainer()
                              ->get(PriceRepository::class)
                              ->findActiveVoucherPrices()
        ;

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form->setValues(['voucherPrice' => $voucherPrice[0]->getId()]);
        unset($form['paymentMethod']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Please select a payment method.');
    }

    public function testIndexFormSubmitWithInvalidPaymentMethod(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);
        $voucherPrice = static::getContainer()
                              ->get(PriceRepository::class)
                              ->findActiveVoucherPrices()
        ;

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form->setValues(['paymentMethod' => 'credit-card']);
        $form->setValues(['voucherPrice' => $voucherPrice[0]->getId()]);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid payment method selected.');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceRedirects(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);
        $voucherPrice = static::getContainer()
                              ->get(PriceRepository::class)
                              ->findActiveVoucherPrices()
        ;

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->setValues([
            'voucherPrice'  => $voucherPrice[0]->getId(),
            'paymentMethod' => 'invoice',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/user/vouchers');
    }

    public function testFormSubmitWithPaymentMethodInvoiceCreatesVouchers(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);
        $voucherPrice = static::getContainer()
                              ->get(PriceRepository::class)
                              ->findActiveVoucherPrices()
        ;

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->setValues([
            'voucherPrice'  => $voucherPrice[0]->getId(),
            'paymentMethod' => 'invoice',
        ]);
        $client->submit($form);

        $vouchers = static::getContainer()
                          ->get(VoucherRepository::class)
                          ->findBy(['user' => $user])
        ;

        self::assertCount($voucherPrice[0]->getVoucherType()->getUnits(), $vouchers);
    }

    public function testFormSubmitWithPaymentMethodInvoiceGeneratesInvoice(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);
        $voucherPrice = static::getContainer()
                              ->get(PriceRepository::class)
                              ->findActiveVoucherPrices()
        ;

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->setValues([
            'voucherPrice'  => $voucherPrice[0]->getId(),
            'paymentMethod' => 'invoice',
        ]);
        $client->submit($form);

        $invoice = static::getContainer()->get(InvoiceRepository::class)
                         ->findOneBy(['user' => $user])
        ;

        $invoiceGenerator = static::getContainer()->get('App\Service\InvoiceGenerator');
        $filePath         = $invoiceGenerator->getTargetDirectory($invoice);
        self::assertFileExists($filePath);
    }

    public function testFormSubmitWithPaymentMethodInvoiceSendsInvoiceByMail(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user           = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);
        $voucherPrice = static::getContainer()
                              ->get(PriceRepository::class)
                              ->findActiveVoucherPrices()
        ;

        $crawler = $client->request('GET', '/voucher');
        $form    = $crawler->filter('form')->form();
        $form->setValues([
            'voucherPrice'  => $voucherPrice[0]->getId(),
            'paymentMethod' => 'invoice',
        ]);
        $client->submit($form);

        $this->assertEmailCount(2);
        $email = $this->getMailerMessage();
        $this->assertEmailAttachmentCount($email, 1);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
