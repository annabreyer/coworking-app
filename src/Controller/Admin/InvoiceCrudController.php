<?php

declare(strict_types = 1);

namespace App\Controller\Admin;

use App\EasyAdmin\InvoicePaymentsField;
use App\Entity\Invoice;
use App\Manager\InvoiceManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\SearchMode;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class InvoiceCrudController extends AbstractCrudController
{
    use ClockAwareTrait;

    public function __construct(
        private readonly InvoiceManager $invoiceManager,
    ) {
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
                     ->setAutofocusSearch(true)
                     ->setSearchFields(['number', 'description', 'payPalOrderId', 'uuid', 'user.lastName'])
                     ->setSearchMode(SearchMode::ALL_TERMS)
                     ->setHelp(
                         'index',
                         'Rechnungen können nicht bearbeitet oder gelöscht werden. <br>Suche nach Rechnungen mit der Nachnamen, Rechnungsnummer, Beschreibung, PayPal-Transaktions-ID oder UUID.'
                     )
                     ->setDefaultSort(['createdAt' => 'DESC'])
                     ->setPaginatorPageSize(20)
                     ->setDateTimeFormat('dd.MM.yyyy HH:mm:ss')
                     ->setDateFormat('dd.MM.yyyy')
                     ->setTimeFormat('HH:mm:ss')
                     ->setNumberFormat('%.2d')
                     ->setDecimalSeparator(',')
                     ->setThousandsSeparator('.')
                     ->hideNullValues()
                     ->showEntityActionsInlined()
                     ->setDefaultSort(['number' => 'DESC'])
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
                     ->add('date')
                     ->add('user')
                     ->add('payments')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $invoiceDownload = Action::new('invoiceDownload', 'Rechnung download')
                                 ->linkToUrl(function (Invoice $invoice) {
                                     return $this->generateUrl('invoice_download', ['uuid' => $invoice->getUuid()]);
                                 })
                                 ->setIcon('fa fa-file-pdf-o')
                                 ->setHtmlAttributes(['target' => '_blank'])
        ;

        $invoiceRegeneration = Action::new('invoiceRegeneration', 'Rechnung neu generieren')
                                     ->linkToCrudAction('regenerateInvoice')
                                     ->setIcon('fa fa-refresh')
                                     ->setHtmlAttributes(['class' => 'btn btn-warning'])
        ;

        $addPaymentAction = Action::new('addPayment', 'Zahlung hinzufügen')
                                  ->linkToCrudAction(Action::EDIT)
                                  ->setIcon('fa fa-euro')
                                  ->setHtmlAttributes(['class' => 'btn btn-primary'])
                                  ->displayIf(static function ($entity) {
                                      return false === $entity->isFullyPaid();
                                  })
        ;

        return parent::configureActions($actions)
                     ->remove(Crud::PAGE_INDEX, Action::EDIT)
                     ->remove(Crud::PAGE_INDEX, Action::DELETE)
                     ->remove(Crud::PAGE_DETAIL, Action::EDIT)
                     ->remove(Crud::PAGE_DETAIL, Action::DELETE)
                     ->add(Crud::PAGE_INDEX, $invoiceDownload)
                     ->add(Crud::PAGE_DETAIL, $invoiceDownload)
                     ->add(Crud::PAGE_DETAIL, $addPaymentAction)
                     ->add(Crud::PAGE_INDEX, $addPaymentAction)
                     ->add(Crud::PAGE_DETAIL, $invoiceRegeneration)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield Field::new('number')
                       ->setFormTypeOption('disabled', true)
            ;
            yield Field::new('date');
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
            ;
            yield AssociationField::new('user');
            yield AssociationField::new('payments');
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield IdField::new('id');
            yield Field::new('createdAt');
            yield TextField::new('uuid');
            yield Field::new('number');
            yield Field::new('date');
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
            ;
            yield AssociationField::new('user');
            yield TextField::new('description');
            yield AssociationField::new('bookings');
            yield AssociationField::new('vouchers');
            yield InvoicePaymentsField::new('payments');
            yield TextField::new('payPalOrderId');
        }

        if (Crud::PAGE_NEW === $pageName) {
            yield Field::new('number')
                       ->setFormTypeOption('disabled', true)
            ;
            yield TextField::new('uuid')
                           ->setFormTypeOption('disabled', true)
            ;
            yield AssociationField::new('user')
                                  ->setFormTypeOption('required', true)
                                  ->autocomplete()
            ;
            yield Field::new('date')
                       ->setFormTypeOption('required', true)
            ;
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
                            ->setFormTypeOption('required', true)
            ;
            yield TextField::new('description')
                           ->setFormTypeOption('required', true)
            ;
        }

        if (Crud::PAGE_EDIT === $pageName) {
            yield Field::new('number')
                       ->setFormTypeOption('disabled', true)
            ;
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
                            ->setFormTypeOption('disabled', true)
            ;
            yield CollectionField::new('payments')
                                 ->setEntryIsComplex()
                                 ->renderExpanded()
                                 ->allowAdd()
                                 ->useEntryCrudForm(PaymentCrudController::class)
                                 ->allowDelete(false)
            ;
            yield AssociationField::new('user')
                                  ->setFormTypeOption('disabled', true)
            ;
            yield Field::new('date')
                       ->setFormTypeOption('disabled', true)
            ;
            yield MoneyField::new('amount')
                            ->setCurrency('EUR')
                            ->setFormTypeOption('disabled', true)
            ;
            yield TextField::new('description')
                           ->setFormTypeOption('disabled', true)
            ;
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
        $this->invoiceManager->saveInvoice($entityInstance);

        if (null === $entityInstance->getFilePath()) {
            $this->invoiceManager->generateInvoicePdf($entityInstance);
        }
    }

    public function regenerateInvoice(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $invoice = $context->getEntity()->getInstance();

        if (null === $invoice) {
            throw new \LogicException('Invoice instance is missing.');
        }
        $this->invoiceManager->generateInvoicePdf($invoice);

        $targetUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl()
        ;

        return $this->redirect($targetUrl);
    }
}
