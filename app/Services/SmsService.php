<?php

namespace App\Services;

use App\Models\SmsMessage;
use App\Jobs\SendSms;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{

    public function sendSms($recipients, $content)
    {
        $merchantNumber = env('MOBILE_MONEY_MERCHANT_ID');
        // Concatenate the fields in the specified order
        $signatureData = $merchantNumber . $content . $recipients;

        // Load the private key (store this securely in your .env or config)
        $privateKey = env('MOBILE_MONEY_PRIVATE_KEY');
        if (!$privateKey) {
            throw new \Exception("Private key not configured");
        }

        // Sign the data
        openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64 encode the signature
        $base64Signature = base64_encode($signature);

        $signature = $base64Signature;
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(env('MOBILE_MONEY_API') . '/doSendSms', [
                'merchant_number' => $merchantNumber,
                'recipients' => $recipients,
                'signature' => $signature,
                'content' => $content,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['state'] === 'OK') {
                    return [
                        'success' => true,
                        'message' => $data['message'],
                        'code' => $data['code']
                    ];
                }

                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'API returned error',
                    'code' => $data['code'] ?? 'UNKNOWN'
                ];
            }

            return [
                'success' => false,
                'message' => 'API request failed with status: ' . $response->status(),
                'code' => 'HTTP_' . $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'error' => $e->getMessage(),
                'recipients' => $recipients
            ]);

            return [
                'success' => false,
                'message' => 'Error making API request: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    public function queueSms($to, $message)
    {
        $smsMessage = SmsMessage::create([
            'to' => $to,
            'message' => $message,
            'status' => 'Pending',
        ]);

        SendSms::dispatch($smsMessage);
    }
}
