<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BookingType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BookingTypeFixtures extends Fixture
{
    public const FULL_DAY_REFERENCE = 'bookingType-full-day';
    public const HALF_DAY_REFERENCE = 'bookingType-half-day';

    public function load(ObjectManager $manager): void
    {
        $fullDay = (new BookingType())->setName(BookingType::NAME_FULL_DAY)->setIsActive(true);
        $halfDay = (new BookingType())->setName(BookingType::NAME_HALF_DAY)->setIsActive(true);

        $manager->persist($fullDay);
        $manager->persist($halfDay);
        $manager->flush();

        $this->addReference(self::FULL_DAY_REFERENCE, $fullDay);
        $this->addReference(self::HALF_DAY_REFERENCE, $halfDay);
    }
}
