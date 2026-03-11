<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
$baseUrl = $scheme . '://' . $host;
$placementHandler = $baseUrl . '/index.php';
$appUrl = $baseUrl . '/index.php?build=' . rawurlencode(APP_VERSION);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install NIP Checker</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
</head>
<body>
    <p>Instalacja aplikacji... proszę czekać.</p>
    <script>
        (function () {
            var appUrl = <?php echo json_encode($appUrl, JSON_UNESCAPED_SLASHES); ?>;
            var placementHandler = <?php echo json_encode($placementHandler, JSON_UNESCAPED_SLASHES); ?>;
            var placementsToClear = ["CRM_COMPANY_TAB", "CRM_COMPANY_DETAIL_TAB"];
            var placementToBind = "CRM_COMPANY_DETAIL_TAB";
            var staleHandlers = [
                placementHandler,
                placementHandler + "?build=<?php echo rawurlencode(APP_VERSION); ?>",
                placementHandler + "?build=20260306",
                placementHandler + "?build=20260306-2",
                placementHandler + "?build=20260306-3"
            ];

            if (!window.BX24 || typeof BX24.init !== "function") {
                document.body.innerHTML = "<p>Brak kontekstu Bitrix24. Otwórz ten adres z instalacji Local App.</p>";
                return;
            }

            BX24.init(function () {
                clearPlacements(0, function () {
                    bindPlacement(function () {
                        if (typeof BX24.installFinish === "function") {
                            BX24.installFinish();
                        }
                        window.location.href = appUrl;
                    });
                });
            });

            function clearPlacements(index, done) {
                if (index >= placementsToClear.length) {
                    done();
                    return;
                }
                unbindHandlerVariants(placementsToClear[index], 0, function () {
                    clearPlacements(index + 1, done);
                });
            }

            function unbindHandlerVariants(placement, variantIndex, done) {
                if (variantIndex >= staleHandlers.length) {
                    done();
                    return;
                }
                BX24.callMethod("placement.unbind", {
                    PLACEMENT: placement,
                    HANDLER: staleHandlers[variantIndex]
                }, function () {
                    unbindHandlerVariants(placement, variantIndex + 1, done);
                });
            }

            function bindPlacement(done) {
                BX24.callMethod("placement.bind", {
                    PLACEMENT: placementToBind,
                    HANDLER: placementHandler,
                    TITLE: "NIP"
                }, function () {
                    done();
                });
            }
        })();
    </script>
</body>
</html>
