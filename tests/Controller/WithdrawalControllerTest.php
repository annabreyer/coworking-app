<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\InvoiceFixtures;
use App\Entity\Withdrawal;
use App\Repository\WithdrawalRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class WithdrawalControllerTest extends BaseTestController
{
    private const MOCK_DATE = '2024-04-01 10:00:00';

    public function testWithdrawalPolicyIsPublic(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $client->request('GET', '/widerruf');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('h1', 'Widerrufsbelehrung');
        static::assertSelectorTextContains('#withdrawal-policy', 'binnen vierzehn Tagen ohne Angabe von Gründen');
        static::assertSelectorExists('#withdrawal-model-form');
        static::assertSelectorExists('#withdrawal-policy a[href="/vertrag-widerrufen"]');
    }

    public function testFooterLinksToWithdrawalFunctionOnEveryPage(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('footer a#footer-withdrawal-button', 'Vertrag widerrufen');
        static::assertSelectorExists('footer a[href="/widerruf"]');
    }

    public function testWithdrawalFormIsPublic(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $crawler = $client->request('GET', '/vertrag-widerrufen');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('h1', 'Vertrag widerrufen');
        static::assertSame('', $crawler->filter('#withdrawal-name')->attr('value'));
        static::assertCount(0, $crawler->filter('#withdrawal-form textarea'));
        static::assertSelectorNotExists('datalist#withdrawal-invoices');
    }

    public function testWithdrawalFormIsPrefilledWhenLoggedIn(): void
    {
        $client = $this->setupTestClientAndLogin(self::MOCK_DATE, [InvoiceFixtures::class], 'user.one@annabreyer.dev');

        $crawler = $client->request('GET', '/vertrag-widerrufen');

        static::assertResponseIsSuccessful();
        static::assertSame('user.one@annabreyer.dev', $crawler->filter('#withdrawal-email')->attr('value'));
        static::assertNotSame('', $crawler->filter('#withdrawal-name')->attr('value'));

        $invoiceNumbers = $crawler->filter('datalist#withdrawal-invoices option')->extract(['value']);
        static::assertContains(InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER, $invoiceNumbers);
        static::assertNotContains('CO20230001', $invoiceNumbers);
    }

    public function testWithdrawalFormIsPrefilledWithContractFromQuery(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $crawler = $client->request('GET', '/vertrag-widerrufen?contract=CO20240001');

        static::assertSame('CO20240001', $crawler->filter('#withdrawal-contract')->attr('value'));
    }

    public function testReviewStepAsksForConfirmationWithoutSaving(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [InvoiceFixtures::class]);

        $this->submitWithdrawalForm($client, 'User One', 'user.one@annabreyer.dev', InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER);

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('h1', 'Widerruf prüfen');
        static::assertSelectorTextContains('#withdrawal-confirm-form button', 'Widerruf bestätigen');
        static::assertSelectorTextContains('#withdrawal-confirm-form', 'Hiermit widerrufe ich den von mir abgeschlossenen Vertrag ' . InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER);
        static::assertCount(0, $this->getRepository(WithdrawalRepository::class)->findAll());
        static::assertEmailCount(0);
    }

    public function testConfirmStepSavesWithdrawalAndSendsReceipt(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [InvoiceFixtures::class]);

        $this->submitWithdrawalForm($client, 'User One', 'User.One@annabreyer.dev', InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER);
        $client->submit($client->getCrawler()->filter('#withdrawal-confirm-form')->form());

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('#withdrawal-received', 'am 01.04.2024 um 12:00:00 Uhr');

        $withdrawals = $this->getRepository(WithdrawalRepository::class)->findAll();
        static::assertCount(1, $withdrawals);

        $withdrawal = $withdrawals[0];
        static::assertSame(Withdrawal::STATUS_RECEIVED, $withdrawal->getStatus());
        static::assertSame('User One', $withdrawal->getName());
        static::assertSame(InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER, $withdrawal->getInvoice()?->getNumber());
        static::assertSame('user.one@annabreyer.dev', $withdrawal->getUser()?->getEmail());
        static::assertEquals(new \DateTimeImmutable(self::MOCK_DATE), $withdrawal->getReceivedAt());

        static::assertEmailCount(2);
        $receipt = $this->getMailerMessage(0);
        static::assertEmailAddressContains($receipt, 'to', 'User.One@annabreyer.dev');
        static::assertEmailSubjectContains($receipt, 'Eingangsbestätigung');
        static::assertEmailHtmlBodyContains($receipt, '01.04.2024 um 12:00:00 Uhr');
        static::assertEmailHtmlBodyContains($receipt, 'Vertrag: ' . InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER);

        $adminNotification = $this->getMailerMessage(1);
        static::assertEmailSubjectContains($adminNotification, 'Neuer Widerruf');
    }

    public function testConfirmStepDoesNotLinkInvoiceOfAnotherUser(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [InvoiceFixtures::class]);

        $this->submitWithdrawalForm($client, 'Someone', 'someone@example.com', InvoiceFixtures::STANDARD_BOOKING_INVOICE_NUMBER);
        $client->submit($client->getCrawler()->filter('#withdrawal-confirm-form')->form());

        static::assertResponseIsSuccessful();

        $withdrawal = $this->getRepository(WithdrawalRepository::class)->findAll()[0];
        static::assertNull($withdrawal->getInvoice());
        static::assertNull($withdrawal->getUser());
        static::assertEmailCount(2);
    }

    public function testReceiptEscapesUserInput(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $this->submitWithdrawalForm($client, '<b>Mallory</b>', 'mallory@example.com', '<script>x</script>');
        $client->submit($client->getCrawler()->filter('#withdrawal-confirm-form')->form());

        $receipt = $this->getMailerMessage(0);
        static::assertEmailHtmlBodyNotContains($receipt, '<script>x</script>');
        static::assertEmailHtmlBodyContains($receipt, '&lt;script&gt;x&lt;/script&gt;');
    }

    public function testInvalidEmailIsRejected(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $this->submitWithdrawalForm($client, 'User One', 'not-an-email', 'CO20240001');

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Bitte gib eine gültige E-Mail-Adresse an.');
    }

    public function testMissingContractIsRejected(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $this->submitWithdrawalForm($client, 'User One', 'user.one@annabreyer.dev', '   ');

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Bitte gib an, welchen Vertrag Du widerrufen möchtest');
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->setupTestClient(self::MOCK_DATE, [BasicFixtures::class]);

        $client->request('POST', '/vertrag-widerrufen', [
            'name'              => 'User One',
            'email'             => 'user.one@annabreyer.dev',
            'contractReference' => 'CO20240001',
            'step'              => 'confirm',
            'token'             => 'invalid',
        ]);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertCount(0, $this->getRepository(WithdrawalRepository::class)->findAll());
    }

    private function submitWithdrawalForm(KernelBrowser $client, string $name, string $email, string $contractReference): void
    {
        $crawler = $client->request('GET', '/vertrag-widerrufen');
        $form    = $crawler->filter('#withdrawal-form')->form();
        $form->setValues([
            'name'              => $name,
            'email'             => $email,
            'contractReference' => $contractReference,
        ]);
        $client->submit($form);
    }
}
