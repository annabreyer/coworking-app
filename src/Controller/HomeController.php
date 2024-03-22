<?php declare(strict_types = 1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index()
    {
        return $this->redirect('https://coworking-hahnheim.de/');
    }

}
