<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\WithdrawalCrudController;
use App\DataFixtures\BasicFixtures;
use App\Tests\Controller\BaseTestController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class WithdrawalCrudControllerTest extends BaseTestController
{
    public function testIndexIsAvailable(): void
    {
        $client = $this->setupTestClientAndLogin(
            '2024-03-01',
            [BasicFixtures::class],
            'admin@annabreyer.dev'
        );

        $indexUrl = static::getContainer()->get(AdminUrlGenerator::class)
            ->setController(WithdrawalCrudController::class)
            ->setAction('index')
            ->generateUrl()
        ;
        $client->request('GET', $indexUrl);

        static::assertResponseIsSuccessful();
    }
}
