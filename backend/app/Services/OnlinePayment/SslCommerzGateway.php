<?php

namespace App\Services\OnlinePayment;

use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SSLCommerz v4 (cards, bKash, Nagad, Rocket, internet banking). The redirect back to us is never trusted:
 * every payment is confirmed with the Order Validation API before money is booked.
 */
class SslCommerzGateway
{
    public function __construct(private string $storeId, private string $password, private bool $sandbox) {}

    private function base(): string
    {
        return $this->sandbox ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
    }

    /** Opens a payment session; returns the SSLCommerz page the parent is sent to. */
    public function start(OnlinePayment $payment, array $customer, array $urls): string
    {
        $response = Http::asForm()->timeout(30)->post($this->base().'/gwprocess/v4/api.php', [
            'store_id' => $this->storeId, 'store_passwd' => $this->password,
            'total_amount' => number_format((float) $payment->amount, 2, '.', ''), 'currency' => 'BDT', 'tran_id' => $payment->tran_id,
            'success_url' => $urls['success'], 'fail_url' => $urls['fail'], 'cancel_url' => $urls['cancel'], 'ipn_url' => $urls['ipn'],
            'cus_name' => $customer['name'], 'cus_email' => $customer['email'], 'cus_phone' => $customer['phone'],
            'cus_add1' => $customer['address'], 'cus_city' => 'Dhaka', 'cus_postcode' => '1000', 'cus_country' => 'Bangladesh',
            'product_name' => 'C-STAR therapy and training fees', 'product_category' => 'Healthcare', 'product_profile' => 'non-physical-goods',
            'shipping_method' => 'NO', 'num_of_item' => 1, 'value_a' => (string) $payment->patient_id,
        ]);
        $url = $response->json('GatewayPageURL');
        if (! $response->successful() || $response->json('status') !== 'SUCCESS' || ! $url) {
            throw new RuntimeException('SSLCommerz: '.($response->json('failedreason') ?? "could not start the payment (HTTP {$response->status()})"));
        }

        return $url;
    }

    /** Order Validation API: the only source of truth for an SSLCommerz payment. */
    public function validate(string $valId): array
    {
        $response = Http::timeout(30)->get($this->base().'/validator/api/validationserverAPI.php', [
            'val_id' => $valId, 'store_id' => $this->storeId, 'store_passwd' => $this->password, 'format' => 'json',
        ]);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException("SSLCommerz validation failed (HTTP {$response->status()})");
        }

        return $response->json();
    }
}
