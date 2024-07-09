<?php

declare(strict_types = 1);

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Manager\InvoiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\SearchMode;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Clock\ClockAwareTrait;

class InvoiceCrudController extends AbstractCrudController
{
    use ClockAwareTrait;
    public function __construct(private InvoiceManager $invoiceManager)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Invoice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Rechnung')
            ->setEntityLabelInPlural('Rechnungen')
            ->setSearchFields(['number', 'description', 'payPalOrderId', 'uuid'])
            ->setAutofocusSearch(true)
            ->setHelp('index', 'Rechnungen können nicht bearbeitet oder gelöscht werden. <br>Suche nach Rechnungen mit der Nachnamen, Rechnungsnummer, Beschreibung, PayPal-Transaktions-ID oder UUID.')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setDateTimeFormat('dd.MM.yyyy HH:mm:ss')
            ->setDateFormat('dd.MM.yyyy')
            ->setTimeFormat('HH:mm:ss')
            ->setSearchFields(['number', 'description', 'payPalOrderId', 'uuid', 'user.lastName'])
            ->setSearchMode(SearchMode::ALL_TERMS)
            ->hideNullValues()
            ->showEntityActionsInlined()
            ->setDefaultSort(['number' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $invoiceDownload = Action::new('invoiceDownload', 'Rechnung download')
            ->linkToUrl(function(Invoice $invoice) {
                return $this->generateUrl('invoice_download', ['uuid' => $invoice->getUuid()]);
            })
            ->setIcon('fa fa-file-pdf-o')
            ->setHtmlAttributes(['target' => '_blank'])
            ;

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $invoiceDownload)
            ->add(Crud::PAGE_DETAIL, $invoiceDownload)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield Field::new('number')
                       ->setFormTypeOption('disabled', true)
            ;
            yield Field::new('date');
            yield Field::new('amount');
            yield AssociationField::new('user');
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield IdField::new('id');
            yield Field::new('createdAt');
            yield TextField::new('uuid');
            yield Field::new('number');
            yield Field::new('date');
            yield Field::new('amount');
            yield AssociationField::new('user');
            yield TextField::new('description');
            yield AssociationField::new('bookings');
            yield AssociationField::new('vouchers');
            yield AssociationField::new('payments');
            yield TextField::new('payPalOrderId');
        }

        if (Crud::PAGE_NEW === $pageName) {
            yield Field::new('number')
                       ->setFormTypeOption('disabled', true)
            ;
            yield TextField::new('uuid')
                           ->setFormTypeOption('disabled', true);
            yield AssociationField::new('user')
                ->setFormTypeOption('required', true)
                ->autocomplete();
            yield Field::new('date')
                ->setFormTypeOption('required', true);;
            yield Field::new('amount')
                ->setFormTypeOption('required', true);;
            yield TextField::new('description')
                ->setFormTypeOption('required', true);;
        }
    }

    public function createEntity(string $entityFqcn): Invoice
    {
        $invoice = new Invoice();
        $invoice->setNumber($this->invoiceManager->getInvoiceNumber());
        $invoice->setDate($this->now());

        return $invoice;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityManager->persist($entityInstance);
        $this->invoiceManager->saveInvoice();

        if ($entityInstance->getDescription()) {
            $this->invoiceManager->generateGeneralInvoicePdf($entityInstance);
        }
    }
}
