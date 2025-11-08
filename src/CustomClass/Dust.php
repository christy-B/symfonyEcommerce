<?php
namespace App\CustomClass;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Dust
{
    private $parameter;
    private $httpClient;
    public function __construct(ParameterBagInterface $parameter, HttpClientInterface $httpClient)
    {
        $this->parameter = $parameter;
        $this->httpClient = $httpClient;
    }

    public function product(array $productData)
    {
        $key = $this->parameter->get('dust_api_key');
        $wId = $this->parameter->get('dust_id');
        $cId = $this->parameter->get('dust_conversation_id');
        $domain = $this->parameter->get('dust_domain');
        $username = $this->parameter->get('dust_username');
        $fullname = $this->parameter->get('dust_fullname');
        $email = $this->parameter->get('dust_email');

        $userContext= [
            "username" => $username,
            "timezone" => "Europe/Paris",
            "fullName" => $fullname,
            "email" => $email,
            "profilePictureUrl" => null,
            "origin" => "api"
        ];

        $payload = [
            'context' => $userContext,
            'content' => sprintf(
                'Tu es un expert en marketing pour un site e-commerce d\'accesoires d\'habillement.
                Ton objectif est de générer une description de produit captivante et optimisée SEO.
                Le produit a les caractéristiques suivantes : 
                - Nom : "%s"
                - Sous-titre : "%s"
                - Catégorie : "%s"
                - Prix : %s EUR
                - Produit phare : %s
                Génère une description **directe**, courte (max 300 caractères), attractive et sans phrases du type "Voici une proposition...".
                Répond uniquement avec le texte final de la description, rien d’autre.',
                $productData['name'] ?? '',
                $productData['subtitle'] ?? '',
                $productData['price'] ?? '',
                $productData['category'] ?? '',
                !empty($productData['isBest']) && $productData['isBest'] ? 'Oui' : 'Non'
            ),
            'mentions' => [
                ['configurationId' => 'gemini-pro']
            ]
        ];

        
        // L'URL d'exécution d'une application Dust
        $urlMsg = "{$domain}/api/v1/w/{$wId}/assistant/conversations/{$cId}/messages";
        $urlCnvt = "{$domain}/api/v1/w/{$wId}/assistant/conversations/{$cId}";

        try {
            $postResponse = $this->httpClient->request('POST', $urlMsg, [
                'headers' => [
                'Authorization' => "Bearer {$key}",
                'Content-Type' => 'application/json',
            ],
                'json' => $payload,
            ]);

            $postData = $postResponse->toArray();
            // On récupère le sId du message de l'Agent (la réponse que l'on attend)
            if (!isset($postData['agentMessages'][0]['sId'])) {
                throw new \Exception("Impossible de récupérer le sId du message de l'agent après POST.");
            }
            $agentMessageSid = $postData['agentMessages'][0]['sId'];

        } catch (\Throwable $e) {
            return "Erreur POST message Dust: " . $e->getMessage();
        }

        // --- 2. GET (Polling) pour récupérer la réponse ---
        $maxAttempts = 15; // 15 secondes max
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(1); // Attendre 1 seconde entre chaque tentative
            
            try {
                $conversationResponse = $this->httpClient->request('GET', $urlCnvt, [
                    'headers' => ['Authorization' => "Bearer {$key}"],
                ]);
                
                $conversationData = $conversationResponse->toArray();
                $turns = $conversationData['conversation']['content'] ?? []; 
                
                // Recherche optimisée : parcourir du message le plus récent au plus ancien
                $reversedTurns = array_reverse($turns);
                foreach ($reversedTurns as $turn) {
                    // Les messages d'un même tour sont dans un sous-tableau, on cherche dans l'ordre inverse
                    $reversedMessages = array_reverse($turn); 
                    
                    foreach ($reversedMessages as $message) {
                        // On vérifie si c'est le message de l'agent qu'on attend et s'il a réussi
                        if (
                            $message['type'] === 'agent_message' &&
                            isset($message['sId']) &&
                            $message['sId'] === $agentMessageSid && 
                            $message['status'] === 'succeeded' &&
                            !empty($message['content'])
                        ) {
                            return trim($message['content']); // Contenu trouvé, on sort
                        }
                        
                        // Si on trouve l'ID de l'agent mais qu'il est toujours en cours, on sort de la recherche actuelle et on continue le polling
                        if ($message['type'] === 'agent_message' && isset($message['sId']) && $message['sId'] === $agentMessageSid) {
                            break 2; // Continuer le polling
                        }
                    }
                }

            } catch (\Throwable $e) {
                return "Erreur GET conversation Dust: " . $e->getMessage();
            }
        }
        
        // Si la boucle se termine sans succès
        return "Délai d'attente dépassé pour la génération de description IA ({$maxAttempts}s).";
    }
}