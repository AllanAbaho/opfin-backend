<?php

namespace App\Services;

use App\Models\SmsMessage;
use App\Jobs\SendSms;
use Illuminate\Support\Facades\Log;

class SmsService
{

    public function sendSms($to, $message)
    {
        // Simulate sending SMS by logging the message
        Log::info("Simulated SMS to {$to}: {$message}");
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
