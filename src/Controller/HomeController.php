<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BookingTypeRepository;
use App\Repository\PriceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(PriceRepository $priceRepository, BookingTypeRepository $bookingTypeRepository): Response
    {
        $voucherPrices = $priceRepository->findActiveVoucherPrices();
        $fullDayType   = $bookingTypeRepository->findFullDayType();
        $unitaryPrice  = $priceRepository->findOneBy(['isUnitary' => true, 'isActive' => true, 'bookingType' => $fullDayType]);

        return $this->render('home/index.html.twig', [
            'voucherPrices' => $voucherPrices,
            'unitaryPrice'  => $unitaryPrice,
        ]);
    }
}
