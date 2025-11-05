<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TailAdminController extends AbstractController
{
    #[Route('/admin/blank', name: 'tailadmin_blank')]
    public function blank(): Response
    {
        return $this->render('tailadmin/pages/blank.html.twig', [
            'pageTitle' => 'Page Vide | TailAdmin',
            'pageName' => 'Blank Page',
            'cardTitle' => 'Titre de la Carte',
            'cardDescription' => 'Commencez à mettre du contenu dans des grilles ou des panneaux, vous pouvez également utiliser différentes combinaisons de grilles. Veuillez consulter le tableau de bord et les autres pages.',
        ]);
    }
}
