<?php
declare(strict_types=1);

// Single source of truth for application release version.
const APP_VERSION = '2026.03.11.1';

// Production endpoints for Bitrix local app settings.
const BITRIX_HANDLER_PATH = 'https://nip.aikuznia.cloud/index.php';
const BITRIX_INITIAL_INSTALLATION_PATH = 'https://nip.aikuznia.cloud/install.php';

// GUS BIR1.1 – główne źródło danych.
// Klucz wyłącznie ze zmiennych środowiskowych.
$gusKey = getenv('GUS_BIR1_KEY') ?: ($_SERVER['GUS_BIR1_KEY'] ?? '');
define('GUS_BIR1_KEY', $gusKey);
define('GUS_BIR1_USE_TEST', filter_var(getenv('GUS_BIR1_USE_TEST') ?: 'false', FILTER_VALIDATE_BOOLEAN));
