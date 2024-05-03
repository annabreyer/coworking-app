<?php

declare(strict_types=1);

namespace App\Tests\Trait;

use App\Trait\EmailContextTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailContextTraitTest extends TestCase
{
    use EmailContextTrait;

    public function testGetStandardEmailContextReturnsBasicContext(): void
    {
        $emailContextTrait = $this->getMockForTrait(EmailContextTrait::class);
        $translator        = $this->createMock(TranslatorInterface::class);
        $translationKey    = 'test';

        $context = $emailContextTrait->getStandardEmailContext($translator, $translationKey);
        static::assertIsArray($context);
        static::assertArrayHasKey('texts', $context);
        static::assertIsArray($context['texts']);
        static::assertArrayHasKey(self::EMAIL_STANDARD_ELEMENT_SALUTATION, $context['texts']);
        static::assertArrayHasKey(self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS, $context['texts']);
        static::assertArrayHasKey(self::EMAIL_STANDARD_ELEMENT_EXPLANATION, $context['texts']);
        static::assertArrayHasKey(self::EMAIL_STANDARD_ELEMENT_SIGNATURE, $context['texts']);
    }
}
