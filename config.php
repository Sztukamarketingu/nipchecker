<?php
declare(strict_types=1);

// Single source of truth for application release version.
const APP_VERSION = '2026.03.06.8';

// Production endpoints for Bitrix local app settings.
const BITRIX_HANDLER_PATH = 'https://nip.aikuznia.cloud/index.php';
const BITRIX_INITIAL_INSTALLATION_PATH = 'https://nip.aikuznia.cloud/install.php';

// GUS BIR1.1 – główne źródło danych.
// Klucz: env GUS_BIR1_KEY, $_SERVER (PassEnv), lub fallback.
$gusKey = getenv('GUS_BIR1_KEY') ?: ($_SERVER['GUS_BIR1_KEY'] ?? '') ?: 'c75480d07a764cc1945f';
define('GUS_BIR1_KEY', $gusKey);
define('GUS_BIR1_USE_TEST', filter_var(getenv('GUS_BIR1_USE_TEST') ?: 'false', FILTER_VALIDATE_BOOLEAN));
