// Opt-in measurements. No request bodies, query values, responses or cookies are recorded.
(function (global) {
    "use strict";
    const storageKey = "aether:performance";
    const option = new URLSearchParams(global.location.search).get("performance");
    let enabled = option === "1";
    try {
        if (option === "0") sessionStorage.removeItem(storageKey);
        else if (enabled) sessionStorage.setItem(storageKey, "1");
        else enabled = sessionStorage.getItem(storageKey) === "1";
    } catch (_) { /* URL activation still works without storage. */ }
    if (!enabled || !global.performance) return;

    const startedAt = new Date().toISOString();
    const records = [];
    const longTasks = [];
    let recording = true;
    const limit = 500;
    const round = (value) => Math.round(value * 10) / 10;
    const now = () => performance.now();
    const append = (list, item) => {
        list.push(item);
        if (list.length > limit) list.shift();
    };
    function safePath(value) {
        try {
            const url = new URL(value, global.location.href);
            if (url.origin !== global.location.origin) return "external:" + url.hostname;
            const match = url.pathname.match(/\/(api\/.*\.php)$/);
            if (match) return match[1];
            if (url.pathname.endsWith("/asset.php")) {
                const asset = url.searchParams.get("path") || "";
                if (/^(?:js|css)\/[A-Za-z0-9._/-]+$/.test(asset)) return "asset.php:" + asset;
            }
            const staticAsset = url.pathname.match(/\/((?:js|css)\/[^/]+)$/);
            if (staticAsset) return staticAsset[1];
            const staticImage = url.pathname.match(/\/(img\/(?:action|tab|mainContentBorder|sidebarContentBorder|aether-logo|paper|leather|goldTab|titleDecoration|paspoort_|stempel-)[A-Za-z0-9._-]*\.(?:png|webp|svg|jpg))$/);
            if (staticImage) return staticImage[1];
            if (/\.(?:php|html)$/.test(url.pathname)) return url.pathname.split("/").pop();
            return "image-or-other-resource";
        } catch (_) { return "unknown-resource"; }
    }
    function measure(label, task, kind = "operation", resultState) {
        if (!recording) return task();
        const start = now();
        function finish(status) {
            if (!recording) return;
            const duration = now() - start;
            const row = { kind, name: label, startMs: round(start), durationMs: round(duration), status };
            append(records, row);
            // Estimate when the updated DOM can paint, without delaying the real operation.
            if (kind !== "api" && !document.hidden) {
                global.requestAnimationFrame(() => {
                    global.setTimeout(() => { if (recording) row.nextFrameMs = round(now() - start); }, 0);
                });
            }
        }
        let result;
        try { result = task(); }
        catch (error) { finish("error"); throw error; }
        return Promise.resolve(result).then((value) => {
            finish(resultState ? resultState(value) : (value === false ? "not-completed" : "completed"));
            return value;
        }, (error) => {
            finish(Number.isInteger(error?.status) ? "http-" + error.status : "error");
            throw error;
        });
    }
    function wrap(name, label, kind = "operation") {
        const original = global[name];
        if (typeof original !== "function") return;
        global[name] = function (...args) {
            const measuredName = kind === "api" ? safePath(args[0]) : label;
            return measure(measuredName, () => original.apply(this, args), kind);
        };
    }
    function install() {
        wrap("apiFetchJson", "", "api");
        wrap("initCharacterPage", "character.page.initialize");
        wrap("getCharacter", "character.open");
        wrap("initCompanyPage", "company.page.initialize");
        wrap("loadCompany", "company.open");
        if (typeof aetherDataCard !== "undefined") {
            const controls = aetherDataCard.controls;
            aetherDataCard.controls = function (header, options) {
                if (typeof options.onSave !== "function") return controls(header, options);
                const save = options.onSave;
                const measuredOptions = Object.assign({}, options, {
                    onSave: function (...args) {
                        return measure("card.save", () => save.apply(this, args), "operation", (value) =>
                            value === false || aetherDataCard.isEditing(options.key) ? "not-saved" : "completed");
                    }
                });
                return controls(header, measuredOptions);
            };
        }
        console.info("Aether-meting actief. Gebruik AetherPerformance.report() of AetherPerformance.download().");
    }
    let observer;
    if (global.PerformanceObserver) {
        try {
            observer = new PerformanceObserver((entries) => {
                if (!recording) return;
                for (const entry of entries.getEntries()) {
                    append(longTasks, { startMs: round(entry.startTime), durationMs: round(entry.duration) });
                }
            });
            observer.observe({ type: "longtask", buffered: true });
        } catch (_) { /* Not every browser supports long-task measurement. */ }
    }
    function snapshot() {
        const navigation = performance.getEntriesByType("navigation")[0];
        const resources = performance.getEntriesByType("resource").slice(-limit).map((entry) => ({
            name: safePath(entry.name), kind: entry.initiatorType,
            startMs: round(entry.startTime), durationMs: round(entry.duration),
            waitingMs: round(entry.responseStart > 0 ? entry.responseStart - entry.startTime : 0),
            transferBytes: entry.transferSize, decodedBytes: entry.decodedBodySize,
            redirectMs: round(entry.redirectEnd - entry.redirectStart)
        }));
        return {
            schemaVersion: 1, startedAt, page: safePath(global.location.href),
            navigation: navigation ? {
                type: navigation.type, firstByteMs: round(navigation.responseStart),
                domReadyMs: round(navigation.domContentLoadedEventEnd),
                loadMs: round(navigation.loadEventEnd),
                transferBytes: navigation.transferSize
            } : null,
            paint: performance.getEntriesByType("paint").map((entry) => ({ name: entry.name, timeMs: round(entry.startTime) })),
            operations: records.map((row) => Object.assign({}, row)), resources,
            longTasks: longTasks.slice(),
            notes: [
                "Durations overlap: do not add them to calculate page load time.",
                "API durations include network, server and JSON parsing; they do not isolate database time.",
                "Operation completion does not include unrelated fire-and-forget requests.",
                "nextFrameMs estimates a paint opportunity, not verified visual completion.",
                "Zero resource sizes can mean caching or unavailable cross-origin timing data.",
                "Only the last 500 measurements per category are retained."
            ]
        };
    }
    global.AetherPerformance = {
        snapshot,
        report() {
            const result = snapshot();
            console.table(result.navigation ? [result.navigation] : []);
            console.table(result.operations);
            console.table(result.resources.slice().sort((a, b) => b.durationMs - a.durationMs).slice(0, 20));
            console.table(result.longTasks);
            return result;
        },
        download() {
            const blob = new Blob([JSON.stringify(snapshot(), null, 2)], { type: "application/json" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = "aether-performance-" + new Date().toISOString().replace(/[:.]/g, "-") + ".json";
            document.body.appendChild(link);
            link.click();
            link.remove();
            global.setTimeout(() => URL.revokeObjectURL(url), 1000);
        },
        stop() {
            recording = false;
            try { sessionStorage.removeItem(storageKey); } catch (_) { /* storage unavailable */ }
            observer?.disconnect();
            console.info("Meting gestopt. Bestaande resultaten blijven beschikbaar. Verwijder performance=1 uit de URL voor volgende pagina's.");
        }
    };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", install, { once: true });
    else install();
})(window);
