<?php

return [
    'chunk_size' => 900,
    'chunk_overlap' => 120,
    'retrieval_limit' => 5,
    'retrieval_candidates' => 250,
    'minimum_score' => 0.08,

    'platform_documents' => [
        [
            'type' => 'refund_policy',
            'title' => 'Refund Policy',
            'content' => 'Customers can request a refund for eligible products within 7 days of delivery. Products must be unused, in original packaging, and include proof of purchase. Refunds are processed after inspection. Delivery charges may be non-refundable unless the return is caused by a platform or seller error.',
        ],
        [
            'type' => 'delivery_policy',
            'title' => 'Delivery Policy',
            'content' => 'Delivery times depend on customer location, product availability, and courier capacity. Orders are shipped after payment confirmation. Delivery charges are calculated from active delivery zones and shown during checkout.',
        ],
        [
            'type' => 'faq',
            'title' => 'Shopping FAQ',
            'content' => 'Customers can compare products by asking about price, category, brand, specifications, stock, and product descriptions available in the catalog. The assistant only answers using stored catalog and platform policy data.',
        ],
    ],
];
