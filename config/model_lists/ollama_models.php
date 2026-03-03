<?php
return [
    [
        'active' => true,

        // MUSS exakt mit `ollama list` übereinstimmen
        'id' => 'llama3.1',

        // Anzeigename im UI
        'label' => 'Llama 3.1 (Ollama – local)',

        // unterstützte Ein- und Ausgaben
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],

        // Feature-Flags
        'tools' => [
            'stream' => true,
            'vision' => false,
            'file_upload' => true,
        ],

        // Ollama Endpoint (Docker-kompatibel)
        'endpoint' => 'http://host.docker.internal:11434',

        // Kontextfenster (realistisch für llama3.1)
        'context_length' => 8192,
    ],
];
