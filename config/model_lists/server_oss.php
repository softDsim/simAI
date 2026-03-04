<?php
return [
    [
        'active' => true,
        'id' => 'openai/gpt-oss-20b',
        'label' => 'Internal OSS (20B)',
        'input' => ['text'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'file_upload' => true,
        ],
    ],
];
