<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;

class SmsMessagesController extends Controller
{
    public function index()
    {
        $smsMessages = SmsMessage::latest()
            ->paginate(15);

        return view('sms-messages.index', compact('smsMessages'));
    }
}
