<?php

return [
    // The split applied to every verified batch. Must sum to 1.0 --
    // SettlementService asserts it, because drift here is money.
    'household_share' => (float) env('ECOCYCLE_HOUSEHOLD_SHARE', 0.60),
    'agent_share'     => (float) env('ECOCYCLE_AGENT_SHARE', 0.40),

    // Declared-vs-verified variance beyond this raises an integrity flag.
    'variance_tolerance' => (float) env('ECOCYCLE_VARIANCE_TOLERANCE', 5.0),

    // A new area opens once this many households register within its radius.
    'area_activation_threshold' => 200,

    // How far from the facility a device location still counts as inside the
    // network. Lagos State sits inside 80 km of Ojota; a fix from further out is
    // a phone somewhere else, not a pickup, and is refused rather than stored.
    'service_radius_km' => (float) env('ECOCYCLE_SERVICE_RADIUS_KM', 80),

    'openai' => [
        'key'          => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),

        // Analytics only phrases figures we already computed, so a small fast
        // model is the right tool -- and the cheap one.
        'model'        => env('OPENAI_MODEL', 'gpt-4o-mini'),

        // Classification reads a photograph, which needs a vision model.
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o-mini'),
    ],

    'facility' => [
        'name'    => 'EcoCycle Lagos Facility',
        'address' => 'Plot 14, Ojota Industrial Layout, Lagos',
        'lat'     => 6.5794,
        'lng'     => 3.3792,
    ],
];
