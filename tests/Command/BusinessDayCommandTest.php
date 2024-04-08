<?php

declare(strict_types=1);

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BusinessDayCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testBusinessDayCommandOutputsArgument(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $command     = $application->find('app:business-days');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'endDate' => '20240101',
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('You passed an argument: 20240101', $output);
    }

    public function testBusinessDayCommandChecksDateFormat(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $command     = $application->find('app:business-days');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'endDate' => '2024/01/01',
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Invalid date format. Please use Ymd', $output);
        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    public function testBusinessDayCommandCreatesBusinessDayUntilGivenDate(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command     = $application->find('app:business-days');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'endDate' => '20240401',
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('BusinessDays will be generated until: 20240401', $output);
        self::assertStringContainsString('Business Days have been generated!', $output);
        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testBusinessDayCommandCreatesBusinessDayUntilSixMonthFromNow(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-01-01'));
        static::bootKernel();

        $application   = new Application(self::$kernel);
        $command       = $application->find('app:business-days');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('BusinessDays will be generated until: 20240701', $output);
        self::assertStringContainsString('Business Days have been generated!', $output);
        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }
}
