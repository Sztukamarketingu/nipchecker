(function () {
    var FIELD_CONFIG = [
        { source: "name", label: "Pełna nazwa firmy", defaults: ["TITLE"] },
        { source: "nip", label: "NIP", defaults: ["UF_CRM_NIP", "UF_CRM_123_NIP", "COMMENTS"] },
        { source: "regon", label: "REGON", defaults: ["COMMENTS"] },
        { source: "country", label: "Kraj", defaults: ["ADDRESS_COUNTRY"] },
        { source: "voivodeship", label: "Województwo", defaults: [] },
        { source: "county", label: "Powiat", defaults: [] },
        { source: "municipality", label: "Gmina", defaults: [] },
        { source: "city", label: "Miasto", defaults: ["ADDRESS_CITY"] },
        { source: "postOffice", label: "Poczta", defaults: [] },
        { source: "street", label: "Ulica", defaults: ["ADDRESS"] },
        { source: "buildingNumber", label: "Nr budynku", defaults: ["ADDRESS_2"] },
        { source: "apartmentNumber", label: "Nr lokalu", defaults: ["ADDRESS_2"] },
        { source: "postalCode", label: "Kod pocztowy", defaults: ["ADDRESS_POSTAL_CODE"] },
        { source: "type", label: "Typ (P/F)", defaults: [] }
    ];

    var state = {
        activeTab: "appTab",
        step: 1,
        companyFields: null,
        mapping: {},
        lastResult: null,
        currentCompanyId: null,
        placementInfo: null,
        started: false,
        inBitrixContext: false
    };

    bootstrap();

    function bootstrap() {
        if (!window.BX24 || typeof BX24.init !== "function") {
            startApp();
            return;
        }

        var startedByBitrix = false;
        BX24.init(function () {
            startedByBitrix = true;
            state.inBitrixContext = true;
            startApp();
        });

        // Fallback for local preview when callback is never triggered.
        setTimeout(function () {
            if (!startedByBitrix) {
                startApp();
            }
        }, 1200);
    }

    function startApp() {
        if (state.started) {
            return;
        }
        state.started = true;
        bindTabs();
        bindAppActions();
        bindSettingsActions();
        detectPlacementContext();
        loadCompanyFieldsAndBuildSettings();
        renderStep(1);
        renderResults(null);
    }

    function bindTabs() {
        var items = document.querySelectorAll(".app-top-nav .ui-nav-item");
        items.forEach(function (item) {
            item.addEventListener("click", function (event) {
                event.preventDefault();
                var tabId = item.getAttribute("data-tab");
                switchTab(tabId);
            });
        });
    }

    function switchTab(tabId) {
        state.activeTab = tabId;
        document.querySelectorAll(".app-top-nav .ui-nav-item").forEach(function (node) {
            node.classList.toggle("ui-nav-item-active", node.getAttribute("data-tab") === tabId);
        });
        document.querySelectorAll(".tab-content").forEach(function (section) {
            section.classList.toggle("tab-hidden", section.id !== tabId);
        });
    }

    function bindAppActions() {
        byId("searchNipBtn").addEventListener("click", searchNip);
        byId("backToStep1Btn").addEventListener("click", function () {
            renderStep(1);
        });
        byId("createCompanyBtn").addEventListener("click", function () {
            saveToCrm("create");
        });
        byId("updateCompanyBtn").addEventListener("click", function () {
            saveToCrm("update");
        });
        byId("restartFlowBtn").addEventListener("click", function () {
            byId("nipInput").value = "";
            state.lastResult = null;
            byId("duplicateWarning").classList.add("step-hidden");
            renderResults(null);
            renderStep(1);
        });
    }

    function bindSettingsActions() {
        byId("saveSettingsBtn").addEventListener("click", function () {
            state.mapping = collectMappingFromUI();
            localStorage.setItem("nipApp.mapping", JSON.stringify(state.mapping));
            byId("settingsMessage").textContent = "Ustawienia zapisane.";
        });
        byId("resetSettingsBtn").addEventListener("click", function () {
            state.mapping = getDefaultMapping(state.companyFields || {});
            localStorage.setItem("nipApp.mapping", JSON.stringify(state.mapping));
            populateMappingUI();
            byId("settingsMessage").textContent = "Przywrócono mapowanie domyślne.";
        });
        byId("uninstallPlacementBtn").addEventListener("click", uninstallPlacements);
    }

    function detectPlacementContext() {
        if (!state.inBitrixContext || !window.BX24) {
            state.placementInfo = {};
            state.currentCompanyId = null;
            return;
        }
        var placement = BX24.placement && BX24.placement.info ? BX24.placement.info() : {};
        state.placementInfo = placement || {};
        var options = state.placementInfo.options || {};
        state.currentCompanyId = extractCompanyId(options);
    }

    function loadCompanyFieldsAndBuildSettings() {
        if (!state.inBitrixContext) {
            state.companyFields = {
                TITLE: { title: "Nazwa firmy" },
                ADDRESS: { title: "Ulica i numer" },
                ADDRESS_2: { title: "Adres (linia 2)" },
                ADDRESS_CITY: { title: "Miasto" },
                ADDRESS_COUNTRY: { title: "Kraj" },
                ADDRESS_POSTAL_CODE: { title: "Kod pocztowy" },
                COMMENTS: { title: "Komentarz" },
                UF_CRM_NIP: { title: "NIP (custom)" }
            };
            state.mapping = loadSavedMapping() || getDefaultMapping(state.companyFields);
            populateMappingUI();
            byId("settingsMessage").textContent = "Tryb lokalny: zapis do CRM działa tylko wewnątrz Bitrix24.";
            return;
        }

        BX24.callMethod("crm.company.fields", {}, function (result) {
            if (result.error()) {
                byId("settingsMessage").textContent = "Nie udało się pobrać pól CRM.";
                return;
            }
            state.companyFields = result.data() || {};
            state.mapping = loadSavedMapping() || getDefaultMapping(state.companyFields);
            populateMappingUI();
        });
    }

    function populateMappingUI() {
        var container = byId("mappingContainer");
        container.innerHTML = "";
        var fieldEntries = Object.entries(state.companyFields || {});
        fieldEntries.sort(function (a, b) {
            return (a[1].title || a[0]).localeCompare(b[1].title || b[0]);
        });

        FIELD_CONFIG.forEach(function (config) {
            var row = document.createElement("div");
            row.className = "map-row";

            var label = document.createElement("label");
            label.textContent = config.label + " (" + config.source + ")";
            label.setAttribute("for", "map-" + config.source);

            var ctl = document.createElement("div");
            ctl.className = "ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100";

            var select = document.createElement("select");
            select.className = "ui-ctl-element";
            select.id = "map-" + config.source;

            var emptyOption = document.createElement("option");
            emptyOption.value = "";
            emptyOption.textContent = "-- pomiń --";
            select.appendChild(emptyOption);

            fieldEntries.forEach(function (entry) {
                var code = entry[0];
                var meta = entry[1];
                var option = document.createElement("option");
                option.value = code;
                option.textContent = pickBestLabel(meta || {}, code);
                select.appendChild(option);
            });

            var mappedValue = state.mapping[config.source] || "";
            if (mappedValue) {
                select.value = mappedValue;
            }

            ctl.appendChild(select);
            row.appendChild(label);
            row.appendChild(ctl);
            container.appendChild(row);
        });
    }

    function getDefaultMapping(fields) {
        var mapping = {};
        FIELD_CONFIG.forEach(function (config) {
            var selected = "";
            config.defaults.some(function (candidate) {
                if (fields[candidate]) {
                    selected = candidate;
                    return true;
                }
                return false;
            });
            if (!selected && config.source === "nip") {
                selected = findLikelyNipField(fields);
            }
            if (selected) {
                mapping[config.source] = selected;
            }
        });
        return mapping;
    }

    function loadSavedMapping() {
        try {
            var raw = localStorage.getItem("nipApp.mapping");
            return raw ? JSON.parse(raw) : null;
        } catch (_error) {
            return null;
        }
    }

    function searchNip() {
        var nip = String(byId("nipInput").value || "").replace(/\D/g, "");
        if (!/^\d{10}$/.test(nip)) {
            alert("Podaj poprawny NIP (10 cyfr).");
            return;
        }

        byId("searchNipBtn").classList.add("ui-btn-clock");
        fetch("handler.php?nip=" + encodeURIComponent(nip), {
            headers: { "Accept": "application/json" }
        })
            .then(function (response) {
                return response.text().then(function (bodyText) {
                    var payload;
                    try {
                        payload = JSON.parse(bodyText);
                    } catch (_error) {
                        throw new Error("Nie udało się odczytać odpowiedzi API.");
                    }

                    if (!response.ok) {
                        throw new Error(payload.error || ("Błąd HTTP " + response.status));
                    }

                    return payload;
                });
            })
            .then(function (payload) {
                byId("searchNipBtn").classList.remove("ui-btn-clock");
                if (payload.error) {
                    throw new Error(payload.error);
                }
                if (payload.found === false || !payload.company) {
                    state.lastResult = null;
                    renderResults(null);
                    alert("Nie znaleziono w MF (Biała Lista) ani w GUS. Sprawdź poprawność NIP.");
                    return;
                }
                state.lastResult = payload;
                renderResults(payload);
                byId("duplicateWarning").classList.add("step-hidden");
                renderStep(2);
            })
            .catch(function (error) {
                byId("searchNipBtn").classList.remove("ui-btn-clock");
                alert("Błąd pobierania danych: " + error.message);
            });
    }

    function renderResults(data) {
        var grid = byId("resultsGrid");
        grid.innerHTML = "";
        var rawBox = byId("rawDataBox");

        if (!data || !data.company) {
            if (rawBox) rawBox.textContent = "Brak danych";
            return;
        }

        FIELD_CONFIG.forEach(function (config) {
            var field = document.createElement("div");
            field.className = "readonly-field";

            var label = document.createElement("div");
            label.className = "readonly-label";
            label.textContent = config.label;

            var value = document.createElement("div");
            value.className = "readonly-value";
            value.textContent = data.company[config.source] || "-";

            field.appendChild(label);
            field.appendChild(value);
            grid.appendChild(field);
        });

        if (rawBox) {
            rawBox.textContent = data.raw ? JSON.stringify(data.raw, null, 2) : "Brak danych surowych";
        }
    }

    function saveToCrm(mode) {
        // Use current UI selections even when user forgot to click "Zapisz ustawienia".
        state.mapping = collectMappingFromUI();

        if (!state.inBitrixContext) {
            alert("Ta funkcja działa tylko w aplikacji osadzonej w Bitrix24.");
            return;
        }
        if (!state.lastResult || !state.lastResult.company) {
            alert("Najpierw pobierz dane firmy.");
            return;
        }

        var crmFields = buildCrmPayload(state.lastResult.company);
        if (!Object.keys(crmFields).length) {
            alert("Brak mapowania pól. Uzupełnij zakładkę Settings.");
            return;
        }

        checkDuplicateByNip(state.lastResult.company.nip, function (duplicateId) {
            if (mode === "create") {
                if (duplicateId) {
                    showDuplicateWarning("Firma o tym NIP już istnieje (ID: " + duplicateId + "). Użyj aktualizacji.");
                    return;
                }
                BX24.callMethod("crm.company.add", { fields: crmFields }, function (result) {
                    if (result.error()) {
                        alert("Błąd tworzenia firmy: " + result.error());
                        return;
                    }
                    completeSuccess("Utworzono nową firmę. ID: " + result.data());
                });
                return;
            }

            var targetId = state.currentCompanyId || duplicateId;
            if (!targetId) {
                alert("Brak kontekstu firmy. Uruchom aplikację z karty firmy lub użyj Utwórz firmę.");
                return;
            }
            BX24.callMethod("crm.company.update", { id: targetId, fields: crmFields }, function (result) {
                if (result.error()) {
                    alert("Błąd aktualizacji firmy: " + result.error());
                    return;
                }
                completeSuccess("Zaktualizowano firmę ID: " + targetId + ".");
            });
        });
    }

    function buildCrmPayload(source) {
        var payload = {};
        Object.keys(state.mapping || {}).forEach(function (sourceKey) {
            var crmField = state.mapping[sourceKey];
            if (!crmField || !source[sourceKey]) {
                return;
            }
            payload[crmField] = source[sourceKey];
        });

        // Keep CRM company title in sync even when mapping points elsewhere.
        if (source.name) {
            payload.TITLE = source.name;
        }
        return payload;
    }

    function checkDuplicateByNip(nip, callback) {
        var candidates = [];
        if (state.mapping && state.mapping.nip) {
            candidates.push(state.mapping.nip);
        }
        ["UF_CRM_NIP", "UF_CRM_123_NIP", "COMMENTS"].forEach(function (fieldCode) {
            if (candidates.indexOf(fieldCode) === -1) {
                candidates.push(fieldCode);
            }
        });

        tryDuplicateField(candidates, nip, callback);
    }

    function tryDuplicateField(fields, nip, callback) {
        if (!fields.length) {
            callback(null);
            return;
        }

        var fieldCode = fields[0];
        var filter = {};
        filter["=" + fieldCode] = nip;

        BX24.callMethod(
            "crm.company.list",
            {
                select: ["ID", fieldCode],
                filter: filter
            },
            function (result) {
                if (!result.error()) {
                    var rows = result.data() || [];
                    if (rows.length) {
                        callback(toNumber(rows[0].ID));
                        return;
                    }
                }
                tryDuplicateField(fields.slice(1), nip, callback);
            }
        );
    }

    function showDuplicateWarning(message) {
        var node = byId("duplicateWarning");
        node.textContent = message;
        node.classList.remove("step-hidden");
    }

    function completeSuccess(message) {
        byId("saveResultMessage").textContent = message;
        renderStep(3);
    }

    function renderStep(stepNumber) {
        state.step = stepNumber;
        document.querySelectorAll(".step-content").forEach(function (node) {
            var step = Number(node.getAttribute("data-step"));
            node.classList.toggle("step-hidden", step !== stepNumber);
        });
        document.querySelectorAll(".app-stepper .ui-step-item").forEach(function (indicator) {
            var step = Number(indicator.getAttribute("data-step-indicator"));
            indicator.classList.toggle("ui-step-item-active", step === stepNumber);
            indicator.classList.toggle("ui-step-item-success", step < stepNumber);
        });
    }

    function toNumber(value) {
        if (typeof value === "string") {
            var match = value.match(/(\d+)/);
            if (match) {
                value = match[1];
            }
        }
        var parsed = Number(value);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    }

    function extractCompanyId(options) {
        var candidates = [
            options.ID,
            options.id,
            options.COMPANY_ID,
            options.entityId,
            options.ENTITY_ID,
            options.ENTITYID
        ];

        for (var i = 0; i < candidates.length; i += 1) {
            var parsed = toNumber(candidates[i]);
            if (parsed) {
                return parsed;
            }
        }
        return null;
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function collectMappingFromUI() {
        var mapping = {};
        FIELD_CONFIG.forEach(function (config) {
            var node = byId("map-" + config.source);
            var value = node ? node.value : "";
            if (value) {
                mapping[config.source] = value;
            }
        });
        return mapping;
    }

    function pickBestLabel(meta, fallbackCode) {
        var candidates = [
            meta.displayTitle,
            meta.title,
            meta.formLabel,
            meta.listLabel,
            meta.EDIT_FORM_LABEL,
            meta.LIST_COLUMN_LABEL,
            meta.LIST_FILTER_LABEL,
            meta.formLabel,
            meta.listLabel,
            meta.name
        ];
        for (var i = 0; i < candidates.length; i += 1) {
            var value = normalizeLabelValue(candidates[i]);
            if (value) {
                return value;
            }
        }
        return fallbackCode;
    }

    function normalizeLabelValue(value) {
        if (!value) {
            return "";
        }
        if (typeof value === "string") {
            return value.trim();
        }
        if (typeof value === "object") {
            return (
                value.pl ||
                value.PL ||
                value.en ||
                value.EN ||
                value.ru ||
                value.RU ||
                ""
            ).trim();
        }
        return String(value).trim();
    }

    function findLikelyNipField(fields) {
        var entries = Object.entries(fields || {});
        for (var i = 0; i < entries.length; i += 1) {
            var code = entries[i][0];
            var meta = entries[i][1] || {};
            var label = (pickBestLabel(meta, code) + " " + code).toLowerCase();
            if (label.indexOf("nip") !== -1) {
                return code;
            }
        }
        return "";
    }

    function uninstallPlacements() {
        if (!state.inBitrixContext || !window.BX24) {
            alert("Odinstalowanie zakładki działa tylko wewnątrz Bitrix24.");
            return;
        }
        var confirmed = window.confirm("Na pewno usunąć zakładkę NIP z karty firmy?");
        if (!confirmed) {
            return;
        }

        var baseHandler = window.location.origin + "/index.php";
        var handlers = [
            baseHandler,
            baseHandler + "?build=20260306",
            baseHandler + "?build=20260306-2",
            baseHandler + "?build=20260306-3"
        ];
        var placements = ["CRM_COMPANY_TAB", "CRM_COMPANY_DETAIL_TAB"];

        unbindPlacementChain(placements, handlers, function () {
            byId("settingsMessage").textContent =
                "Zakładka NIP została odinstalowana. Odśwież kartę firmy (F5), aby zobaczyć zmianę.";
        });
    }

    function unbindPlacementChain(placements, handlers, done) {
        var pIndex = 0;
        var hIndex = 0;

        function next() {
            if (pIndex >= placements.length) {
                done();
                return;
            }
            if (hIndex >= handlers.length) {
                pIndex += 1;
                hIndex = 0;
                next();
                return;
            }
            BX24.callMethod("placement.unbind", {
                PLACEMENT: placements[pIndex],
                HANDLER: handlers[hIndex]
            }, function () {
                hIndex += 1;
                next();
            });
        }

        next();
    }
})();