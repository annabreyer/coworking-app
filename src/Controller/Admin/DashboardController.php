<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use App\Entity\Booking;
use App\Entity\BusinessDay;
use App\Entity\Invoice;
use App\Entity\Price;
use App\Entity\Room;
use App\Entity\TermsOfUse;
use App\Entity\User;
use App\Entity\UserAction;
use App\Entity\Voucher;
use App\Entity\VoucherType;
use App\Entity\WorkStation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        parent::index();

        return $this->render('admin/dashboard.html.twig');
    }

    public function configureCrud(): Crud
    {
        return parent::configureCrud()
                     ->showEntityActionsInlined();
    }

    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, 'detail');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Coworking Administration');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('User Administration');
        yield MenuItem::linkToCrud('User', 'fas fa-user', User::class);
        yield MenuItem::linkToCrud('Terms of Use', 'fas fa-file', TermsOfUse::class);
        yield MenuItem::linkToCrud('UserAction', 'fas fa-home', UserAction::class);
        yield MenuItem::linkToCrud('AdminAction', 'fas fa-home', AdminAction::class);
        yield MenuItem::section('Room Administration');
        yield MenuItem::linkToCrud('BusinessDays', 'fas fa-calendar', BusinessDay::class);
        yield MenuItem::linkToCrud('Room', 'fas fa-door-open', Room::class);
        yield MenuItem::linkToCrud('Workstation', 'fas fa-chair', WorkStation::class);
        yield MenuItem::linkToCrud('Booking', 'fas fa-file-contract', Booking::class);
        yield MenuItem::section('Money Administration');
        yield MenuItem::linkToCrud('Price', 'fas fa-euro-sign', Price::class);
        yield MenuItem::linkToCrud('Voucher', 'fas fa-vote-yea', Voucher::class);
        yield MenuItem::linkToCrud('VoucherType', 'fas fa-vote-yea', VoucherType::class);
        yield MenuItem::linkToCrud('Invoice', 'fas fa-money-bill-wave', Invoice::class);
    }

    public function configureAssets(): Assets
    {
        return parent::configureAssets()
                     ->addCssFile('css/admin.css');
    }
}
