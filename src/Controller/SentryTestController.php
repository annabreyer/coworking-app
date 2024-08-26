<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class SentryTestController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    #[Route('/_sentry-test', name: 'sentry_test')]
    public function testLog(): void
    {
        // the following code will test if monolog integration logs to sentry
        $this->logger->error('My custom logged error.', ['some' => 'Context Data']);

        // the following code will test if an uncaught exception logs to sentry
        throw new \RuntimeException('Example exception.');
    }
}
