<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://api.bitrix24.com/api/v1/"></script>
    <link rel="stylesheet" href="https://training.bitrix24.com/bitrix/js/ui/design-tokens/dist/ui.design-tokens.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo rawurlencode(APP_VERSION); ?>">
    <style>
        /* Inline fallback for Bitrix iframe (when external CSS is cached/blocked). */
        .tab-hidden { display: none !important; }
        .step-hidden { display: none !important; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f1f4f8;
            color: #2f343a;
        }
        #app {
            max-width: 1180px;
            margin: 14px auto;
            background: #fff;
            border: 1px solid #dfe0e3;
            border-radius: 10px;
            padding: 0 18px 18px;
            box-sizing: border-box;
        }
        .ui-nav {
            display: flex;
            align-items: center;
            gap: 2px;
            background: #0f8bd6;
            padding: 8px 8px 0;
            border-radius: 0 0 4px 4px;
            margin-bottom: 16px;
        }
        .ui-nav .ui-nav-item {
            text-decoration: none;
            color: #eaf6ff;
            padding: 10px 16px;
            border-radius: 6px 6px 0 0;
            background: rgba(255, 255, 255, 0.12);
            font-weight: 500;
        }
        .ui-nav .ui-nav-item.ui-nav-item-active { color: #0f8bd6; background: #fff; }
        .ui-step { display: flex; align-items: center; gap: 12px; margin: 12px 0 20px; }
        .ui-step-item { display: flex; align-items: center; gap: 8px; color: #a8b0b8; flex: 1; }
        .ui-step-number {
            width: 30px; height: 30px; border-radius: 50%;
            border: 1px solid #cfd6dd; display: inline-flex;
            align-items: center; justify-content: center; font-weight: 600; background: #fff;
        }
        .ui-step-item-active .ui-step-number { border-color: #2f9cf3; background: #2f9cf3; color: #fff; }
        .ui-step-item-success .ui-step-number { border-color: #2fc06e; color: #2fc06e; }
        .ui-ctl { border: 1px solid #d5dbe1; border-radius: 4px; background: #fff; }
        .ui-ctl-element { width: 100%; min-height: 36px; border: 0; outline: 0; padding: 6px 10px; box-sizing: border-box; }
        .ui-btn { border: 1px solid transparent; border-radius: 4px; padding: 8px 14px; cursor: pointer; background: #edf2f6; }
        .ui-btn-primary { background: #2f9cf3; color: #fff; }
        .ui-btn-success { background: #2fc06e; color: #fff; }
        .ui-btn-light-border { background: #fff; border-color: #c9d0d7; color: #2f343a; }
        .nip-search-row { display: flex; gap: 10px; align-items: center; }
        .result-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
        .readonly-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
        .result-form, .vat-box-wrapper, .success-box { padding: 16px; border: 1px solid #dfe0e3; border-radius: 10px; background: #fff; }
        .readonly-value { min-height: 38px; border: 1px solid #dfe0e3; border-radius: 8px; padding: 7px 10px; background: #f7f8f9; }
        .vat-status-box {
            min-height: 240px; border-radius: 10px; padding: 16px; border: 1px solid;
            display: flex; align-items: center; justify-content: center; text-align: center; font-size: 24px;
        }
        .vat-status-active { color: #0f7b43; border-color: #3bcf8e; background: #e8fff3; }
        .vat-status-inactive { color: #ab2538; border-color: #ef6a7b; background: #ffeef1; }
        .vat-status-unknown { color: #5f6c79; border-color: #c5ccd3; background: #f5f7f9; }
        .actions-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; justify-content: flex-end; }
        .hint-text { margin-top: 12px; color: #828b95; }
        .warning-box { margin-top: 10px; padding: 10px; border-radius: 8px; color: #8f5a00; border: 1px solid #f5c96c; background: #fff8e8; }
        .mapping-container { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; margin-top: 12px; }
    </style>
    <title>NIP-GUS Checker</title>
</head>
<body>
    <div id="app">
        <nav class="ui-nav ui-nav-bordered app-top-nav">
            <a href="#" class="ui-nav-item ui-nav-item-active" data-tab="appTab">
                <span class="ui-nav-link">Aplikacja</span>
            </a>
            <a href="#" class="ui-nav-item" data-tab="settingsTab">
                <span class="ui-nav-link">Ustawienia</span>
            </a>
        </nav>

        <section id="appTab" class="tab-content">
            <div class="ui-step app-stepper">
                <div class="ui-step-item ui-step-item-active" data-step-indicator="1">
                    <div class="ui-step-number">1</div>
                    <div class="ui-step-title">Szukaj</div>
                </div>
                <div class="ui-step-item" data-step-indicator="2">
                    <div class="ui-step-number">2</div>
                    <div class="ui-step-title">Zweryfikuj</div>
                </div>
                <div class="ui-step-item" data-step-indicator="3">
                    <div class="ui-step-number">3</div>
                    <div class="ui-step-title">Zatwierdź</div>
                </div>
            </div>

            <div class="step-content" data-step="1">
                <div class="nip-search-row">
                    <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                        <input id="nipInput" type="text" class="ui-ctl-element" maxlength="10" placeholder="Wpisz NIP (10 cyfr)">
                    </div>
                    <button id="searchNipBtn" class="ui-btn ui-btn-primary">
                        <span class="ui-btn-text">Szukaj</span>
                    </button>
                </div>
                <p class="hint-text">
                    Aplikacja umożliwia stworzenie lub aktualizację danych firmy na podstawie numeru NIP
                    (MF Biała Lista, fallback GUS BIR1).
                </p>
            </div>

            <div class="step-content step-hidden" data-step="2">
                <div class="result-layout">
                    <div class="result-form">
                        <h3 class="section-title">Dane firmy</h3>
                        <div class="readonly-grid" id="resultsGrid"></div>
                    </div>
                    <aside class="vat-box-wrapper">
                        <h3 class="section-title">VAT Status</h3>
                        <div id="vatStatusBox" class="vat-status-box vat-status-unknown">
                            Brak danych
                        </div>
                    </aside>
                </div>
                <div class="actions-row">
                    <button id="backToStep1Btn" class="ui-btn ui-btn-light-border">
                        <span class="ui-btn-text">Wstecz</span>
                    </button>
                    <button id="updateCompanyBtn" class="ui-btn ui-btn-primary">
                        <span class="ui-btn-text">Zaktualizuj obecną firmę</span>
                    </button>
                    <button id="createCompanyBtn" class="ui-btn ui-btn-success">
                        <span class="ui-btn-text">Utwórz firmę</span>
                    </button>
                </div>
                <div id="duplicateWarning" class="warning-box step-hidden"></div>
            </div>

            <div class="step-content step-hidden" data-step="3">
                <div class="success-box">
                    <h3>Operacja zakończona sukcesem</h3>
                    <p id="saveResultMessage">Dane firmy zostały zapisane w CRM.</p>
                    <button id="restartFlowBtn" class="ui-btn ui-btn-light-border">
                        <span class="ui-btn-text">Wyszukaj kolejny NIP</span>
                    </button>
                </div>
            </div>
        </section>

        <section id="settingsTab" class="tab-content tab-hidden">
            <h3 class="section-title">Mapowanie pól</h3>
            <p class="hint-text">Wybierz pola CRM dla danych pobieranych z MF / GUS.</p>
            <div id="mappingContainer" class="mapping-container"></div>
            <div class="actions-row">
                <button id="saveSettingsBtn" class="ui-btn ui-btn-primary">
                    <span class="ui-btn-text">Zapisz ustawienia</span>
                </button>
                <button id="resetSettingsBtn" class="ui-btn ui-btn-light-border">
                    <span class="ui-btn-text">Przywróć domyślne</span>
                </button>
                <button id="uninstallPlacementBtn" class="ui-btn ui-btn-light-border">
                    <span class="ui-btn-text">Odinstaluj zakładkę NIP</span>
                </button>
            </div>
            <div id="settingsMessage" class="hint-text"></div>
        </section>

    </div>
    <script src="app.js?v=<?php echo rawurlencode(APP_VERSION); ?>"></script>
</body>
</html>