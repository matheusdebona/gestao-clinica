<?php

return [
    /*
    | Leave empty to auto-detect common Chromium/Chrome and Node paths.
    | Docker image sets BROWSERSHOT_CHROME_PATH=/usr/bin/chromium.
    | VPS PHP-FPM: see README "PDF de orçamento (Browsershot)".
    */
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
    'node_module_path' => env('BROWSERSHOT_NODE_MODULE_PATH'),
    'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 60),
];
