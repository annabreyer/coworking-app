<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\BookingCrudController;
use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\Repository\BookingRepository;
use App\Tests\Controller\BaseTestController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class BookingCrudControllerTest extends BaseTestController
{
    public function testCancelBookingRedirectsToBookingIndex(): void
    {
        $client = $this->setupTestClientAndLogin(
            '2024-03-01',
            [BookingWithInvoiceNoPaymentFixture::class],
            'admin@annabreyer.dev'
        );

        $indexUrl = static::getContainer()->get(AdminUrlGenerator::class)
            ->setController(BookingCrudController::class)
            ->setAction('index')
            ->generateUrl()
        ;
        $crawler    = $client->request('GET', $indexUrl);
        $cancelLink = $crawler->filter('a.action-cancelBooking')->first();
        $bookingId  = (int) $cancelLink->closest('tr')->attr('data-id');

        $client->request('GET', $cancelLink->attr('href'));
        static::assertResponseRedirects();

        $client->followRedirect();
        static::assertResponseStatusCodeSame(Response::HTTP_OK);
        static::assertStringContainsString('crudAction=index', $client->getRequest()->getUri());

        $booking = static::getContainer()->get(BookingRepository::class)->find($bookingId);
        static::assertTrue($booking->isCancelled());
    }
}
