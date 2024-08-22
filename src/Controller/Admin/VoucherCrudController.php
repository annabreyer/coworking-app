<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Voucher;
use App\Manager\InvoiceManager;
use App\Manager\VoucherManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use Symfony\Component\Clock\ClockAwareTrait;

class VoucherCrudController extends AbstractCrudController
{
    use ClockAwareTrait;

    public function __construct(private readonly InvoiceManager $invoiceManager)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Voucher::class;
    }

    public function configureCrud($crud): Crud
    {
        return parent::configureCrud($crud)
                     ->setEntityLabelInSingular('Gutschein')
                     ->setEntityLabelInPlural('Mehrfachkarten/Gutscheine')
                     ->setSearchFields(['code', 'value', 'voucherType.name', 'user.lastName'])
                     ->setDefaultSort(['createdAt' => 'DESC'])
                     ->setHelp(
                         'index',
                         'Einzelgutscheine und Mehrfachkarten können über "Erstellen" erstellt werden. <br>
                             Suche nach Gutscheinen mit der Nachnamen, Gutscheincode, Wert oder Gutscheinart.<br>
                             Bei NICHT genutzten Gutscheinen kann nur der Wert, Das Nutzungsdatum und das Verfallsdatum geändert werden.<br>
                             Bei genutzten Gutscheinen kann nichts geändert werden.
                             Gutscheine können nur gelöscht werden, sofern sie keine Rechnung haben und nicht benutzt wurden. <br>'
                     )
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->update(Crud::PAGE_INDEX, Action::DELETE, static function (Action $action) {
                return $action->displayIf(static function (Voucher $voucher) {
                    return null === $voucher->getInvoice() && null === $voucher->getUseDate();
                });
            });
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName || Crud::PAGE_DETAIL === $pageName) {
            yield Field::new('code');
            yield MoneyField::new('value')
                            ->setCurrency('EUR')
            ;
            yield AssociationField::new('voucherType');
            yield AssociationField::new('user');
            yield DateField::new('createdAt');
            yield Field::new('expiryDate');
            yield Field::new('useDate');
            yield BooleanField::new('isFullyPaid')
                              ->renderAsSwitch(false)
            ;
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield AssociationField::new('invoice');
        }

        if (Crud::PAGE_NEW === $pageName) {
            yield AssociationField::new('voucherType')
                                  ->setFormTypeOption('query_builder', static function (EntityRepository $entityRepository) {
                                      return $entityRepository->createQueryBuilder('vt')
                                                              ->andWhere('vt.isActive = true')
                                                              ->orderBy('vt.name', 'ASC')
                                      ;
                                  })
                                  ->setRequired(true)
                                  ->setHelp('Um einen einzelnen Gutschein zu erstellen, wähle einen individuellen Typ (evtl. musst Du ihn vorher anlegen). Für eine Mehrfachkarte wähle den entsprechenden Typ.')
            ;
            yield AssociationField::new('user')
                                  ->setRequired(true)
                                  ->autocomplete()
            ;
            yield MoneyField::new('value')
                            ->setCurrency('EUR')
                            ->setHelp('Lass diese Feld leer, um den Standardwert des Gutscheintyps zu verwenden.')
                            ->setRequired(false)
            ;
            yield DateField::new('expiryDate')
                           ->setHelp('Wenn das aktuelle Datum eingegeben ist, wird die Gültigkeit des Gutscheintyps verwendet.')
                           ->setRequired(false)
            ;
            yield Field::new('code')
                       ->setFormTypeOption('disabled', true)
            ;
            yield MoneyField::new('invoiceAmount')
                            ->setCurrency('EUR')
                            ->setRequired(true)
                            ->setHelp('Rechnung wird automatisch mit diesem Betrag erstellt.')
            ;
        }

        if (Crud::PAGE_EDIT === $pageName) {
            /** @var Voucher $voucher */
            $voucher = $this->getContext()->getEntity()->getInstance();

            yield FormField::addFieldset('Modifizierbare Daten')
                           ->setHelp('Bei genutzten Gutscheinen kann nichts geändert werden.<br>
                                            Bei NICHT genutzten Gutscheinen kann nur der Wert, das Nutzungsdatum und das Verfallsdatum geändert werden.
            ');

            if (null !== $voucher->getUseDate()) {
                yield MoneyField::new('value')
                                ->setCurrency('EUR')
                                ->setDisabled()
                ;
                yield Field::new('expiryDate')
                           ->setDisabled()
                ;
            } else {
                yield MoneyField::new('value')
                                ->setCurrency('EUR')
                ;
                yield DateField::new('expiryDate');
                yield DateField::new('useDate');
            }

            yield FormField::addFieldset('Nicht modifizierbare Daten')
            ;
            yield Field::new('code')
                       ->setDisabled()
            ;
            yield AssociationField::new('voucherType')
                                  ->setDisabled()
            ;
            yield AssociationField::new('user')
                                  ->setDisabled();
            yield DateField::new('createdAt')
                           ->setDisabled();
            yield AssociationField::new('invoice')
                                  ->setDisabled();
        }
    }

    public function createEntity(string $entityFqcn): Voucher
    {
        $voucher = new Voucher();
        $voucher->setCode(VoucherManager::generateVoucherCode());
        $voucher->setExpiryDate($this->now());

        return $voucher;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Voucher) {
            return;
        }

        if ($this->now() === $entityInstance->getExpiryDate()) {
            $entityInstance->setExpiryDate(
                VoucherManager::calculateExpiryDate(
                    $this->now(),
                    $entityInstance->getVoucherType()->getValidityMonths()
                )
            );
        }

        if (null === $entityInstance->getValue()) {
            $entityInstance->setValue($entityInstance->getVoucherType()->getUnitaryValue());
        }

        $invoice = $this->invoiceManager->createInvoice($entityInstance->getUser(), $entityInstance->getInvoiceAmount(), false);

        if (1 === $entityInstance->getVoucherType()->getUnits()) {
            $entityInstance->setInvoice($invoice);
            $entityManager->persist($entityInstance);
            $entityManager->flush();

            return;
        }

        $vouchers = VoucherManager::createVouchers(
            $entityInstance->getUser(),
            $entityInstance->getVoucherType(),
            $entityInstance->getVoucherType()->getUnits(),
            $entityInstance->getValue(),
            $entityInstance->getExpiryDate()
        );

        foreach ($vouchers as $voucher) {
            $voucher->setInvoice($invoice);
            $entityManager->persist($voucher);
        }

        $entityManager->flush();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Voucher) {
            return;
        }

        if (null === $entityInstance->getInvoice() && null === $entityInstance->getUseDate()) {
            parent::deleteEntity($entityManager, $entityInstance);
        }
    }
}
