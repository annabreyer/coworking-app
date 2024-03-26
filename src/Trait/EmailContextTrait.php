<?php

declare(strict_types=1);

namespace App\Trait;

use Symfony\Contracts\Translation\TranslatorInterface;

trait EmailContextTrait
{
    /**
     * @param TranslatorInterface $translator
     * @param string              $emailTranslationKey
     *
     * @return array<string, mixed>
     */
    public function getStandardEmailContext(TranslatorInterface $translator, string $emailTranslationKey): array
    {
        return [
            'texts' => [
                'salutation'   => $translator->trans('email.' . $emailTranslationKey . '.salutation'),
                'instructions' => $translator->trans('email.' . $emailTranslationKey . '.instructions'),
                'explanation'  => $translator->trans('email.' . $emailTranslationKey . '.explanation'),
                'signature'    => $translator->trans('email.' . $emailTranslationKey . '.signature'),
            ],
        ];
    }
}
