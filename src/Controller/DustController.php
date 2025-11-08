<?php

namespace App\Controller;

use App\CustomClass\Dust;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DustController extends AbstractController
{
    #[Route('/dust', name: 'app_dust', methods: ['POST'])]
    public function index(Dust $dust, Request $request): JsonResponse
    {
        // Récupère les données JSON envoyées depuis le formulaire
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['response' => 'Aucune donnée reçue'], 400);
        }

        // Appelle le service Dust avec le tableau complet
        $result = $dust->product($data);

        // Nettoyage des sauts de ligne
        $clean = str_replace(["\n", "\r"], ' ', $result);

        // Retour JSON pour le JS
        return $this->json(
            ['response' => $clean],
            200,
            [],
            ['json_encode_options' => JSON_UNESCAPED_UNICODE]
        );
    }
}
