<?php
/**
 * Czyszczenie zakładek NIP – usuwa starą zakładkę (pusty ekran).
 * Otwórz z karty firmy w Bitrix24 (zakładka NIP → Ustawienia → link).
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config.php';

$placements = ['CRM_COMPANY_DETAIL_TAB', 'CRM_COMPANY_TAB'];
$baseUrl = 'https://nip.aikuznia.cloud';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Czyszczenie zakładek NIP</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; }
        .handler { padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9; }
        .handler-url { font-size: 12px; color: #666; word-break: break-all; }
        .btn { padding: 8px 16px; margin: 4px; cursor: pointer; border-radius: 4px; border: 1px solid #ccc; background: #fff; }
        .btn-danger { background: #dc3545; color: white; border-color: #dc3545; }
        .msg { padding: 12px; margin: 12px 0; border-radius: 8px; }
        .msg-success { background: #d4edda; color: #155724; }
        .msg-error { background: #f8d7da; color: #721c24; }
        .msg-info { background: #d1ecf1; color: #0c5460; }
        input[type=text] { width: 100%; padding: 8px; margin: 8px 0; box-sizing: border-box; }
    </style>
</head>
<body>
    <h1>Czyszczenie zakładek NIP</h1>
    <p>Jeśli masz dwie zakładki NIP (jedna pusta), usuń starą poniżej.</p>
    <div id="msg"></div>
    <div id="list"></div>
    <hr>
    <h3>Ręczne odinstalowanie</h3>
    <p>Jeśli znasz URL starej zakładki (np. ngrok), wklej go i odinstaluj:</p>
    <input type="text" id="manualUrl" placeholder="https://xxx.ngrok.io/index.php">
    <button class="btn btn-danger" onclick="unbindManual()">Odinstaluj ten URL</button>
    <p><a href="index.php">← Powrót do aplikacji</a></p>

    <script>
        (function () {
            var placements = <?php echo json_encode($placements); ?>;
            var currentHandler = '<?php echo $baseUrl; ?>/index.php';

            if (!window.BX24 || typeof BX24.init !== "function") {
                document.getElementById("msg").innerHTML = '<div class="msg msg-error">Otwórz tę stronę z zakładki NIP w Bitrix24 (Ustawienia → link „Czyszczenie zakładek”).</div>';
                return;
            }

            BX24.init(function () {
                loadHandlers(0, []);
            });

            function loadHandlers(placementIndex, allHandlers) {
                if (placementIndex >= placements.length) {
                    renderList(allHandlers);
                    return;
                }
                var placement = placements[placementIndex];
                BX24.callMethod("placement.get", { PLACEMENT: placement }, function (r) {
                    var data = [];
                    try {
                        var raw = r.data ? r.data() : (r.result || r);
                        if (Array.isArray(raw)) data = raw;
                        else if (raw && raw[placement]) data = Array.isArray(raw[placement]) ? raw[placement] : [raw[placement]];
                        else if (raw && typeof raw === 'object') data = [raw];
                    } catch (e) {}
                    data.forEach(function (h) {
                        var handler = (typeof h === 'string') ? h : (h.handler || h.HANDLER || h.url || '');
                        if (handler) allHandlers.push({ placement: placement, handler: String(handler), title: (h && (h.title || h.TITLE)) || "NIP" });
                    });
                    loadHandlers(placementIndex + 1, allHandlers);
                });
            }

            function renderList(handlers) {
                var list = document.getElementById("list");
                if (!handlers.length) {
                    list.innerHTML = '<div class="msg msg-info">Nie znaleziono powiązanych zakładek. Użyj formularza poniżej, jeśli znasz URL starej zakładki.</div>';
                    return;
                }
                list.innerHTML = handlers.map(function (h) {
                    var isCurrent = h.handler.indexOf("nip.aikuznia.cloud") !== -1;
                    var safeHandler = h.handler.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
                    return '<div class="handler">' +
                        '<strong>' + (h.title || "NIP") + '</strong> (' + h.placement + ')<br>' +
                        '<span class="handler-url">' + escapeHtml(h.handler) + '</span><br>' +
                        (isCurrent ? '<span style="color:green">✓ Działająca</span>' :
                            '<button class="btn btn-danger" onclick="doUnbind(\'' + h.placement.replace(/'/g, "\\'") + '\', \'' + safeHandler + '\')">Odinstaluj</button>') +
                        '</div>';
                }).join("");
            }

            function escapeHtml(s) {
                var d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            window.doUnbind = function (placement, handler) {
                BX24.callMethod("placement.unbind", { PLACEMENT: placement, HANDLER: handler }, function (r) {
                    var msg = document.getElementById("msg");
                    if (r.error()) {
                        msg.innerHTML = '<div class="msg msg-error">Błąd: ' + (r.error() || "Nie udało się") + '</div>';
                    } else {
                        msg.innerHTML = '<div class="msg msg-success">Zakładka odinstalowana. Odśwież kartę firmy (F5).</div>';
                        loadHandlers(0, []);
                    }
                });
            };

            window.unbindManual = function () {
                var url = document.getElementById("manualUrl").value.trim();
                if (!url) { alert("Wklej URL zakładki"); return; }
                placements.forEach(function (p) {
                    BX24.callMethod("placement.unbind", { PLACEMENT: p, HANDLER: url }, function (r) {
                        if (!r.error()) {
                            document.getElementById("msg").innerHTML = '<div class="msg msg-success">Odinstalowano. Odśwież kartę firmy (F5).</div>';
                        }
                    });
                });
            };
        })();
    </script>
</body>
</html>
