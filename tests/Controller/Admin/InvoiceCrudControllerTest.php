<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\InvoiceCrudController;
use App\DataFixtures\BookingFixtures;
use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Tests\Controller\BaseTestController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class InvoiceCrudControllerTest extends BaseTestController
{
    private const ADMIN_EMAIL = 'admin@annabreyer.dev';
    private const USER_EMAIL  = 'user.one@annabreyer.dev';

    public function testDownloadFilteredInvoicesReturnsZipWithAllInvoices(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BookingWithInvoiceNoPaymentFixture::class], self::ADMIN_EMAIL);

        $client->request('GET', $this->getAdminUrl('downloadFilteredInvoices'));

        static::assertResponseStatusCodeSame(Response::HTTP_OK);
        static::assertResponseHeaderSame('content-type', 'application/zip');

        self::assertSame(
            [
                BookingFixtures::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER . '.pdf',
                BookingFixtures::FIRST_BOOKING_INVOICE_NUMBER . '.pdf',
            ],
            $this->getZipEntryNames($client)
        );
    }

    public function testDownloadFilteredInvoicesRespectsSearchQuery(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BookingWithInvoiceNoPaymentFixture::class], self::ADMIN_EMAIL);

        $client->request('GET', $this->getAdminUrl('downloadFilteredInvoices', ['query' => BookingFixtures::FIRST_BOOKING_INVOICE_NUMBER]));

        static::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([BookingFixtures::FIRST_BOOKING_INVOICE_NUMBER . '.pdf'], $this->getZipEntryNames($client));
    }

    public function testDownloadFilteredInvoicesRedirectsWhenNothingMatches(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BookingWithInvoiceNoPaymentFixture::class], self::ADMIN_EMAIL);

        $client->request('GET', $this->getAdminUrl('downloadFilteredInvoices', ['query' => 'does-not-exist']));

        static::assertResponseRedirects();
        $client->followRedirect();
        static::assertSelectorTextContains('.alert-warning', 'Keine Rechnungen zum Herunterladen gefunden.');
    }

    public function testDownloadFilteredInvoicesIsForbiddenForUsers(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BookingWithInvoiceNoPaymentFixture::class], self::USER_EMAIL);

        $client->request('GET', $this->getAdminUrl('downloadFilteredInvoices'));

        static::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testBulkDownloadInvoicesReturnsZipWithSelectedInvoices(): void
    {
        $client  = $this->setupTestClientAndLogin('2024-03-01', [BookingWithInvoiceNoPaymentFixture::class], self::ADMIN_EMAIL);
        $invoice = $this->getInvoice(BookingFixtures::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER);

        $crawler = $client->request('GET', $this->getAdminUrl('index'));
        static::assertResponseIsSuccessful();
        $csrfToken = $crawler->filter('[data-action-name="bulkInvoiceDownload"]')->attr('data-action-csrf-token');
        self::assertNotEmpty($csrfToken);

        $client->request('POST', $this->getAdminUrl('bulkDownloadInvoices'), [
            'batchActionName'      => 'bulkInvoiceDownload',
            'entityFqcn'           => Invoice::class,
            'batchActionUrl'       => $this->getAdminUrl('bulkDownloadInvoices'),
            'batchActionCsrfToken' => $csrfToken,
            'batchActionEntityIds' => [$invoice->getId()],
        ]);

        static::assertResponseStatusCodeSame(Response::HTTP_OK);
        static::assertResponseHeaderSame('content-type', 'application/zip');
        self::assertSame([$invoice->getNumber() . '.pdf'], $this->getZipEntryNames($client));
    }

    public function testBulkDownloadInvoicesRejectsInvalidCsrfToken(): void
    {
        $client  = $this->setupTestClientAndLogin('2024-03-01', [BookingWithInvoiceNoPaymentFixture::class], self::ADMIN_EMAIL);
        $invoice = $this->getInvoice(BookingFixtures::BOOKING_WITH_INVOICE_NO_PAYMENT_INVOICE_NUMBER);

        $client->request('POST', $this->getAdminUrl('bulkDownloadInvoices'), [
            'batchActionName'      => 'bulkInvoiceDownload',
            'entityFqcn'           => Invoice::class,
            'batchActionUrl'       => $this->getAdminUrl('bulkDownloadInvoices'),
            'batchActionCsrfToken' => 'invalid',
            'batchActionEntityIds' => [$invoice->getId()],
        ]);

        static::assertResponseRedirects('/admin');
    }

    private function getAdminUrl(string $crudAction, array $parameters = []): string
    {
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class)
            ->setController(InvoiceCrudController::class)
            ->setAction($crudAction)
        ;

        foreach ($parameters as $name => $value) {
            $adminUrlGenerator->set($name, $value);
        }

        return $adminUrlGenerator->generateUrl();
    }

    private function getInvoice(string $number): Invoice
    {
        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => $number]);
        self::assertInstanceOf(Invoice::class, $invoice);

        return $invoice;
    }

    /**
     * @return string[]
     */
    private function getZipEntryNames(KernelBrowser $client): array
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'invoice_archive_test_');
        file_put_contents($archivePath, $client->getInternalResponse()->getContent());

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archivePath));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($archivePath);

        sort($names);

        return $names;
    }
}
