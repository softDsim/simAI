<?php

return [
    // Das weiterhin bestehende GPT-OSS-20b
    [
        'active' => true,
        'id' => 'gpt-oss-20b', // Stelle sicher, dass die ID exakt mit dem Server übereinstimmt
        'label' => 'Internal OSS (20B)',
        'input' => ['text'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => true,
        ],
    ],
    // Das neue multimodale Modell
    [
        'active' => true,
        'id' => 'pixtral-12b', // ID gemäß Server-Endpunkt
        'label' => 'Pixtral 12B (Multimodal)',
        // WICHTIG: Erlaube Bild-Inputs für die Multimodalität
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => true,
            'vision' => true, // Aktiviert die Bildverarbeitung im Chat-Interface
        ],
    ],
];
