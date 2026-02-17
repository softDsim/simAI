<?php
return [
    [
        'active' => true,
        'id' => 'meta-llama/Llama-3.1-8B-Instruct', // Muss exakt mit dem "model" im curl übereinstimmen
        'label' => 'Internal Llama 3.1 (8B)',
        'input' => ['text'],
        'output' => ['text'],
        'tools' => [
            'stream' => true, // Falls Streaming unterstützt wird, sonst false
            'file_upload' => true,
            'web_search' => false,
        ],
    ],
];
