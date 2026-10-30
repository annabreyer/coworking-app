<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\WithdrawalCrudController;
use App\DataFixtures\BookingWithPaymentFixture;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Withdrawal;
use App\Repository\InvoiceRepository;
use App\Repository\WithdrawalRepository;
use App\Tests\Controller\BaseTestController;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class WithdrawalCrudControllerTest extends BaseTestController
{
    public function testProcessActionIsShownOnlyForReceivedWithdrawalsWithInvoice(): void
    {
        $client = $this->setupTestClientAndLogin('2024-02-25', [BookingWithPaymentFixture::class], 'admin@annabreyer.dev');
        $this->createWithdrawal(true);
        $this->createWithdrawal(false);

        $crawler = $client->request('GET', $this->getAdminUrl(Crud::PAGE_INDEX));

        static::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a.action-processWithdrawal'));
    }

    public function testProcessFutureBookingPaidByTransactionRecordsManualRefund(): void
    {
        $client     = $this->setupTestClientAndLogin('2024-02-25', [BookingWithPaymentFixture::class], 'admin@annabreyer.dev');
        $withdrawal = $this->createWithdrawal(true);

        $this->submitProcessForm($client, $withdrawal);

        $withdrawal = $this->reloadWithdrawal($withdrawal);
        self::assertSame(Withdrawal::STATUS_PROCESSED, $withdrawal->getStatus());
        self::assertStringContainsString('15,00 €', (string) $withdrawal->getAdminComment());
        self::assertTrue($withdrawal->getInvoice()?->getFirstBooking()?->isCancelled());

        $reversalInvoice = $this->getInvoiceRepository()->findOneBy(['amount' => -1500]);
        self::assertInstanceOf(Invoice::class, $reversalInvoice);
        $refundPayment = $reversalInvoice->getPayments()->first();
        self::assertInstanceOf(Payment::class, $refundPayment);
        self::assertSame(Payment::PAYMENT_TYPE_REFUND, $refundPayment->getType());
        self::assertTrue($reversalInvoice->isFullyPaid());

        static::assertEmailCount(1);
        $email = static::getMailerMessage();
        self::assertNotNull($email);
        static::assertEmailHeaderSame($email, 'To', 'user.one@annabreyer.dev');
        static::assertEmailHtmlBodyContains($email, 'Wir überweisen Dir 15,00 €');
    }

    public function testProcessPastBookingRejectsWithdrawal(): void
    {
        $client     = $this->setupTestClientAndLogin('2024-03-05', [BookingWithPaymentFixture::class], 'admin@annabreyer.dev');
        $withdrawal = $this->createWithdrawal(true);

        $this->submitProcessForm($client, $withdrawal);

        $withdrawal = $this->reloadWithdrawal($withdrawal);
        self::assertSame(Withdrawal::STATUS_REJECTED, $withdrawal->getStatus());
        self::assertFalse($withdrawal->getInvoice()?->getFirstBooking()?->isCancelled());
        self::assertNull($this->getInvoiceRepository()->findOneBy(['amount' => -1500]));

        static::assertEmailCount(1);
        $email = static::getMailerMessage();
        self::assertNotNull($email);
        static::assertEmailHtmlBodyContains($email, 'Damit ist Dein Widerrufsrecht erloschen');
    }

    private function submitProcessForm(KernelBrowser $client, Withdrawal $withdrawal): void
    {
        $crawler = $client->request('GET', $this->getAdminUrl('processWithdrawal', $withdrawal->getId()));
        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('body', BookingWithPaymentFixture::INVOICE_NUMBER);

        $form = $crawler->selectButton('Widerruf bearbeiten')->form();
        $client->submit($form);
        static::assertResponseRedirects();
    }

    private function createWithdrawal(bool $withInvoice): Withdrawal
    {
        $invoice = $this->getInvoiceRepository()->findOneBy(['number' => BookingWithPaymentFixture::INVOICE_NUMBER]);
        self::assertInstanceOf(Invoice::class, $invoice);

        $withdrawal = (new Withdrawal())
            ->setName('User One')
            ->setEmail('user.one@annabreyer.dev')
            ->setContractReference(BookingWithPaymentFixture::INVOICE_NUMBER)
            ->setReceivedAt(new \DateTimeImmutable('2024-02-24 12:00:00'))
            ->setInvoice($withInvoice ? $invoice : null)
            ->setUser($invoice->getUser())
        ;

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->persist($withdrawal);
        $entityManager->flush();

        return $withdrawal;
    }

    private function getAdminUrl(string $action, ?int $entityId = null): string
    {
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        self::assertInstanceOf(AdminUrlGenerator::class, $adminUrlGenerator);
        $adminUrlGenerator->setController(WithdrawalCrudController::class)
            ->setAction($action)
        ;

        if (null !== $entityId) {
            $adminUrlGenerator->setEntityId($entityId);
        }

        return $adminUrlGenerator->generateUrl();
    }

    private function reloadWithdrawal(Withdrawal $withdrawal): Withdrawal
    {
        $withdrawal = $this->getWithdrawalRepository()->find($withdrawal->getId());
        self::assertInstanceOf(Withdrawal::class, $withdrawal);

        return $withdrawal;
    }

    private function getWithdrawalRepository(): WithdrawalRepository
    {
        $repository = static::getContainer()->get(WithdrawalRepository::class);
        self::assertInstanceOf(WithdrawalRepository::class, $repository);

        return $repository;
    }

    private function getInvoiceRepository(): InvoiceRepository
    {
        $repository = static::getContainer()->get(InvoiceRepository::class);
        self::assertInstanceOf(InvoiceRepository::class, $repository);

        return $repository;
    }
}
