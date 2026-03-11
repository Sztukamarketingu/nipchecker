<?php
declare(strict_types=1);

// Single source of truth for application release version.
const APP_VERSION = '2026.03.06.3';

// Production endpoints for Bitrix local app settings.
const BITRIX_HANDLER_PATH = 'https://nip.aikuznia.cloud/index.php';
const BITRIX_INITIAL_INSTALLATION_PATH = 'https://nip.aikuznia.cloud/install.php';

// GUS BIR1.1 – fallback when MF (Biała Lista) returns no data.
// Key from env GUS_BIR1_KEY; leave empty to disable GUS fallback.
define('GUS_BIR1_KEY', getenv('GUS_BIR1_KEY') ?: '');
define('GUS_BIR1_USE_TEST', filter_var(getenv('GUS_BIR1_USE_TEST') ?: 'false', FILTER_VALIDATE_BOOLEAN));
