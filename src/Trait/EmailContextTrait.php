<?php

declare(strict_types=1);

namespace App\Trait;

use Symfony\Contracts\Translation\TranslatorInterface;

trait EmailContextTrait
{
    public const EMAIL_STANDARD_ELEMENT_SALUTATION   = 'salutation';
    public const EMAIL_STANDARD_ELEMENT_INSTRUCTIONS = 'instructions';
    public const EMAIL_STANDARD_ELEMENT_EXPLANATION  = 'explanation';
    public const EMAIL_STANDARD_ELEMENT_SIGNATURE    = 'signature';

    /**
     * @return array<string, mixed>
     */
    public function getStandardEmailContext(TranslatorInterface $translator, string $emailTranslationKey): array
    {
        return [
            'texts' => [
                self::EMAIL_STANDARD_ELEMENT_SALUTATION   => $translator->trans('email.' . $emailTranslationKey . '.salutation'),
                self::EMAIL_STANDARD_ELEMENT_INSTRUCTIONS => $translator->trans('email.' . $emailTranslationKey . '.instructions'),
                self::EMAIL_STANDARD_ELEMENT_EXPLANATION  => $translator->trans('email.' . $emailTranslationKey . '.explanation'),
                self::EMAIL_STANDARD_ELEMENT_SIGNATURE    => $translator->trans('email.' . $emailTranslationKey . '.signature'),
            ],
        ];
    }
}
