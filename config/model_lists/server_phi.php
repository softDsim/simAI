<?php
return [
    [
        'active' => true,
        'id' => 'microsoft/Phi-3-medium-128k-instruct',
        'label' => 'Internal Phi-3 (14B)',
        'input' => ['text'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => true,
        ],
        // Hinweis: Das Limit von 70k wird hier dokumentiert, technisch aber vom Server verwaltet
    ],
];
