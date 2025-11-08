<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends AbstractController
{

    #[Route('/commande/create/session/{reference}', name: 'app_stripe_create_session')]
    public function index(EntityManagerInterface $manager, $reference)
    {
        $product_for_stripe = [];
        $YOUR_DOMAIN = $_ENV["DOMAIN_NAME"];
        
        $order = $manager->getRepository(Order::class)->findOneByReference($reference);
        if (!$order) {
            return $this->json(['error' => 'order']);
        }

        //produit
        foreach ($order->getOrderDetails()->getValues() as $product) {
            $product_object = $manager->getRepository(Product::class)->findOneByName($product->getProduct());
            $product_for_stripe[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $product->getPrice(),
                    'product_data' => [
                        'name' => $product->getProduct(),
                        'images' => [$YOUR_DOMAIN . "/assets/illustration/" . $product_object->getIllustration()],
                    ],
                ],
                'quantity' => $product->getQuantity(),
            ];
        }
        //transporteur
        $product_for_stripe[] = [
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $order->getCarrierPrice(),
                'product_data' => [
                    'name' => $order->getCarrierName(),
                    'images' => [$YOUR_DOMAIN],
                ],
            ],
            'quantity' => $product->getQuantity(),
        ];



        Stripe::setApiKey($_ENV["STRIPE_SECRET"]);

        $checkout_session = Session::create([
            'customer_email' => $this->getUser()->getEmail(),
            'payment_method_types' => ['card'],
            'line_items' => [[
                $product_for_stripe
            ]],
            'mode' => 'payment',
            'success_url' => $YOUR_DOMAIN.'/commande/success/{CHECKOUT_SESSION_ID}',
            'cancel_url' => $YOUR_DOMAIN.'/commande/erreur/{CHECKOUT_SESSION_ID}',
        ]);

        $order->setStripeSessionId($checkout_session->id);
        $manager->flush();
        return $this->json(['id' => $checkout_session->id]);
    }
}
