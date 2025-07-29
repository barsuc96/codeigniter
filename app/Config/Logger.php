<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Log\Handlers\FileHandler;

class Logger extends BaseConfig
{
    /**
     * Poziom logowania (więcej = więcej informacji)
     * production: 4 (error, warning)
     * development: 9 (wszystko)
     */
    public int|array $threshold;

    /**
     * Format daty logów
     */
    public string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Obsługa zapisu logów
     */
    public array $handlers;

    public function __construct()
    {
        parent::__construct();

        // Ustawienie poziomu logowania w zależności od środowiska
        $this->threshold = (ENVIRONMENT === 'production') ? 4 : 9;

        // Ścieżka logów z podziałem na środowiska: logs/dev/ lub logs/prod/
        $logPath = realpath(WRITEPATH . 'logs') . DIRECTORY_SEPARATOR .
                   ((ENVIRONMENT === 'production') ? 'prod' : 'dev') . DIRECTORY_SEPARATOR;

        $this->handlers = [
            FileHandler::class => [
                'handles' => [
                    'critical',
                    'alert',
                    'emergency',
                    'debug',
                    'error',
                    'info',
                    'notice',
                    'warning',
                ],
                'fileExtension'   => '',       // np. .log
                'filePermissions' => 0644,
                'path'            => $logPath,
            ],
        ];
    }
}
