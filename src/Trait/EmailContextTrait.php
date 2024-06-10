<?php

declare(strict_types = 1);

namespace App\Trait;

trait EmailContextTrait
{
    public const EMAIL_STANDARD_ELEMENT_SALUTATION   = 'salutation';
    public const EMAIL_STANDARD_ELEMENT_INSTRUCTIONS = 'instructions';
    public const EMAIL_STANDARD_ELEMENT_EXPLANATION  = 'explanation';
    public const EMAIL_STANDARD_ELEMENT_SIGNATURE    = 'signature';
    public const EMAIL_STANDARD_ELEMENT_SUBJECT      = 'subject';
    public const EMAIL_STANDARD_ELEMENT_BUTTON_TEXT    = 'button_text';
}
