<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Wraps Safaricom's Daraja API. getAccessToken() proven working
 * already; stkPush() is BRD Section 8 "Loan repayments (C2B)" via
 * Lipa Na M-Pesa Online -- prompts the client's phone to enter their
 * M-Pesa PIN and pay into the SACCO's till.
 */
class MpesaService
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $baseUrl;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;

    public function __construct()
    {
        $this->consumerKey = config('services.mpesa.consumer_key');
        $this->consumerSecret = config('services.mpesa.consumer_secret');
        $this->shortcode = config('services.mpesa.shortcode');
        $this->passkey = config('services.mpesa.passkey');
        $this->callbackUrl = config('services.mpesa.callback_url');
        $this->baseUrl = config('services.mpesa.env') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function getAccessToken(): string
    {
        return Cache::remember('mpesa_access_token', 3500, function () {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate", ['grant_type' => 'client_credentials']);

            if (!$response->successful()) {
                throw new \RuntimeException('Could not authenticate with M-Pesa: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Initiates an STK push (Lipa Na M-Pesa Online) prompt to the
     * given phone number. Returns Safaricom's response, which includes
     * CheckoutRequestID -- the id we use to match the later callback
     * back to this specific attempt.
     */
    public function stkPush(string $phoneNumber, float $amount, string $accountReference, string $description): array
    {
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) round($amount),
                'PartyA' => $phoneNumber,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $phoneNumber,
                'CallBackURL' => $this->callbackUrl,
                'AccountReference' => $accountReference,
                'TransactionDesc' => $description,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('STK push request failed: ' . $response->body());
        }

        return $response->json();
    }
}
