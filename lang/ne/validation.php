<?php

// Only the rules Kh-aaja uses. Anything missing falls back to English.
return [
    'accepted' => 'कृपया :attribute स्वीकार गर्नुहोस्।',
    'confirmed' => ':attribute मिलेन।',
    'email' => ':attribute सही इमेल ठेगाना हुनुपर्छ।',
    'image' => ':attribute फोटो हुनुपर्छ।',
    'integer' => ':attribute पूर्ण संख्या हुनुपर्छ।',
    'max' => [
        'numeric' => ':attribute :max भन्दा बढी हुनु हुँदैन।',
        'file' => ':attribute :max KB भन्दा ठूलो हुनु हुँदैन।',
        'string' => ':attribute :max अक्षरभन्दा लामो हुनु हुँदैन।',
    ],
    'min' => [
        'numeric' => ':attribute कम्तीमा :min हुनुपर्छ।',
        'string' => ':attribute कम्तीमा :min अक्षरको हुनुपर्छ।',
    ],
    'regex' => ':attribute को ढाँचा मिलेन।',
    'required' => ':attribute चाहिन्छ।',
    'string' => ':attribute अक्षरमा हुनुपर्छ।',
    'unique' => 'यो :attribute पहिले नै प्रयोगमा छ।',

    'attributes' => [
        'name' => 'नाम',
        'email' => 'इमेल',
        'password' => 'पासवर्ड',
        'phone' => 'मोबाइल नम्बर',
        'quantity' => 'संख्या',
        'note' => 'नोट',
        'consent' => 'सहमति',
        'price' => 'मूल्य',
    ],
];
