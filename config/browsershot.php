<?php

return [
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH', '/usr/bin/chromium'),
    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
    'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 60),
];
