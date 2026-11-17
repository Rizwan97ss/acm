<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactInquiryRequest;
use App\Models\ContactInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ContactInquiryController extends Controller
{
    /**
     * Save a landing-page enquiry. Submissions caught by the honeypot get the same
     * response but are not stored, so bots learn nothing.
     */
    public function store(ContactInquiryRequest $request): RedirectResponse
    {
        if ($request->isSpam()) {
            Log::notice('Landing page enquiry rejected as spam.', ['ip' => $request->ip()]);

            return back();
        }

        $inquiry = ContactInquiry::query()->create([
            ...Arr::except($request->validated(), ['website']),
            'ip_address' => $request->ip(),
        ]);

        Log::info('Landing page enquiry received.', ['inquiry_id' => $inquiry->id, 'interest' => $inquiry->interest]);

        return back();
    }
}
