<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\DataFixtures\BasicFixtures;
use App\Tests\Controller\BaseTestController;

class DashboardControllerTest extends BaseTestController
{
    public function testDashboardShowsBookingCalendarUrl(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BasicFixtures::class], 'admin@annabreyer.dev');

        $client->request('GET', '/admin');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextSame(
            '#booking-calendar-url',
            'http://localhost/booking-calendar/test-calendar-token.ics'
        );
    }
}
