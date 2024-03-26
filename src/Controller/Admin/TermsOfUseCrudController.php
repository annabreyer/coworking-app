<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TermsOfUse;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class TermsOfUseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TermsOfUse::class;
    }
}
