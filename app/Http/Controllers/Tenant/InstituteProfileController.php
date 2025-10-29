<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\InstituteProfile;
use Illuminate\Http\Request;

class InstituteProfileController extends Controller
{
    /**
     * GET /api/v1/institute/profile
     * বর্তমান প্রোফাইল দেখাবে (names + contact)
     */
    public function show()
    {
        $p = InstituteProfile::singleton();

        return response()->json([
            'names'   => $p->names,   // { en?, bn?, ar? }
            'contact' => $p->contact, // { address, phone?, email?, website?, social?{...} }
        ]);
    }

    /**
     * PUT/PATCH /api/v1/institute/profile
     * শুধুমাত্র names/contact আপডেট করবে
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'names' => ['nullable', 'array'],
            'names.en' => ['nullable', 'string', 'max:150'],
            'names.bn' => ['nullable', 'string', 'max:150'],
            'names.ar' => ['nullable', 'string', 'max:150'],

            'contact' => ['required', 'array'],
            'contact.address' => ['required', 'string', 'max:500'], // একলাইন/মাল্টিলাইন ঠিকানা
            'contact.phone'   => ['nullable', 'string', 'max:40'],
            'contact.email'   => ['nullable', 'email', 'max:150'],
            'contact.website' => ['nullable', 'url', 'max:200'],

            'contact.social'           => ['nullable', 'array'],
            'contact.social.facebook'  => ['nullable', 'url', 'max:200'],
            'contact.social.youtube'   => ['nullable', 'url', 'max:200'],
            'contact.social.whatsapp'  => ['nullable', 'url', 'max:200'],
        ]);

        $p = InstituteProfile::singleton();

        // names পুরোটা রিপ্লেস (nullable হলে null রাখতে দিন)
        if (array_key_exists('names', $data)) {
            $p->names = $data['names'];
        }

        // contact পার্শিয়াল আপডেট সাপোর্ট: পুরোনোর সাথে মিশিয়ে নেব
        $newContact = $data['contact'] ?? [];
        $existing   = $p->contact ?? [];
        $p->contact = array_replace_recursive($existing, $newContact);

        $p->save();

        return response()->json([
            'names'   => $p->names,
            'contact' => $p->contact,
        ]);
    }
}
