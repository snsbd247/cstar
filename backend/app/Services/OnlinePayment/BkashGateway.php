<?php

namespace App\Services\OnlinePayment;

use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * bKash Tokenized Checkout (v1.2.0-beta): grant token → create → parent pays on bKash → execute.
 * A payment counts only when execute (or the status query) reports transactionStatus "Completed".
 */
class BkashGateway
{
    public function __construct(private string $appKey, private string $appSecret, private string $username, private string $password, private bool $sandbox) {}

    private function base(): string
    {
        return ($this->sandbox ? 'https://tokenized.sandbox.bka.sh' : 'https://tokenized.pay.bka.sh').'/v1.2.0-beta/tokenized/checkout';
    }

    private function token(): string
    {
        return Cache::remember('bkash.id_token.'.md5($this->appKey.($this->sandbox ? 's' : 'l')), now()->addMinutes(50), function () {
            $response = Http::acceptJson()->timeout(30)->withHeaders(['username' => $this->username, 'password' => $this->password])
                ->post($this->base().'/token/grant', ['app_key' => $this->appKey, 'app_secret' => $this->appSecret]);
            $token = $response->json('id_token');
            if (! $response->successful() || ! $token) {
                throw new RuntimeException('bKash: '.($response->json('statusMessage') ?? $response->json('msg') ?? "could not sign in (HTTP {$response->status()})"));
            }

            return $token;
        });
    }

    private function call(string $path, array $body): array
    {
        $response = Http::acceptJson()->timeout(30)->withHeaders(['Authorization' => $this->token(), 'X-App-Key' => $this->appKey])->post($this->base().$path, $body);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException("bKash {$path} failed (HTTP {$response->status()})");
        }

        return $response->json();
    }

    /** @return array{paymentID: string, bkashURL: string} */
    public function create(OnlinePayment $payment, string $payer, string $callbackUrl): array
    {
        $data = $this->call('/create', [
            'mode' => '0011', 'payerReference' => $payer, 'callbackURL' => $callbackUrl,
            'amount' => number_format((float) $payment->amount, 2, '.', ''), 'currency' => 'BDT', 'intent' => 'sale', 'merchantInvoiceNumber' => $payment->tran_id,
        ]);
        if (empty($data['paymentID']) || empty($data['bkashURL'])) {
            throw new RuntimeException('bKash: '.($data['statusMessage'] ?? 'could not start the payment'));
        }

        return ['paymentID' => $data['paymentID'], 'bkashURL' => $data['bkashURL']];
    }

    public function execute(string $paymentId): array
    {
        return $this->call('/execute', ['paymentID' => $paymentId]);
    }

    public function query(string $paymentId): array
    {
        return $this->call('/payment/status', ['paymentID' => $paymentId]);
    }
}
