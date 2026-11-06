(() => {
    "use strict";
    const fields = [
        ["companyValue", "Bedrijfswaarde", "money"],
        ["issuedShareCount", "Uitgegeven aandelen", "integer"],
        ["shareMarketPrice", "Marktprijs per aandeel", "money"],
        ["stability", "Stabiliteit", "score"],
        ["profitability", "Rendabiliteit", "score"]
    ];
    const svgNS = "http://www.w3.org/2000/svg";
    let companyId = 0, quarters = [], requestId = 0, active = null, saving = false;
    let editMode = false, editType = "drag", dragDraft = null, chartSaving = false, paintPreview = null;
    let pending = new Map(), firstVisible = 0, lastVisible = -1;
    let startPeriod = null, endPeriod = null, startYear = "", endYear = "";
    let horizontalZoom = 1, verticalZoom = 1;
    let snapshotEventOptions = [], existingSnapshotIds = new Set(), foundationGameDate = "";
    let latestRender = Promise.resolve();
    let canEdit = false;

    function gameDate(outgameDate) {
        const date = String(outgameDate || "").slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return "";
        return `${String(Number(date.slice(0, 4)) - 100).padStart(4, "0")}${date.slice(4)}`;
    }
    function quarterIndexForGameDate(date) {
        return Number(date.slice(0, 4)) * 4 + Math.floor((Number(date.slice(5, 7)) - 1) / 3);
    }
    function annotateMissingSnapshots() {
        const firstIndex = quarters.length ? quarters[0].year * 4 + quarters[0].quarter - 1 : 0;
        quarters.forEach(row => { row.pendingSnapshotEvents = []; });
        snapshotEventOptions.forEach(event => {
            if (existingSnapshotIds.has(Number(event.idEvent))) return;
            const startDate = gameDate(event.dateStart);
            const endDate = gameDate(event.dateEnd || event.dateStart) || startDate;
            if (!startDate || !endDate || endDate <= foundationGameDate) return;
            const start = quarterIndexForGameDate(startDate);
            const end = quarterIndexForGameDate(endDate);
            for (let absolute = Math.max(firstIndex, start); absolute <= Math.min(end, firstIndex + quarters.length - 1); absolute++) {
                const row = quarters[absolute - firstIndex];
                if (row && row.year * 4 + row.quarter - 1 === absolute && row.source !== "snapshot")
                    row.pendingSnapshotEvents.push(event);
            }
        });
    }

    function node(tag, className = "", label = "") {
        const result = document.createElement(tag);
        if (className) result.className = className;
        if (label) result.textContent = label;
        return result;
    }
    function svgNode(tag, attributes = {}) {
        const result = document.createElementNS(svgNS, tag);
        Object.entries(attributes).forEach(([key, value]) => result.setAttribute(key, String(value)));
        return result;
    }
    function amount(value, kind) {
        if (value === null || value === undefined) return "Onbekend";
        const number = Number(value || 0);
        return new Intl.NumberFormat("nl-BE", {
            minimumFractionDigits: kind === "money" ? 2 : 0,
            maximumFractionDigits: kind === "money" ? 2 : 0
        }).format(number) + (kind === "money" ? " Fr" : "");
    }
    function label(row) { return `${row.year} · K${row.quarter}`; }

    function editStatus(message = "", error = false) {
        const status = document.getElementById("companyQuarterEditStatus");
        status.textContent = message;
        status.classList.toggle("d-none", !message);
        status.classList.toggle("text-danger", error);
    }

    function rowAt(index) { return pending.get(index) || quarters[index]; }
    function eventSignature(items) {
        return JSON.stringify((items || []).map(({ description, publicity }) => ({ description, publicity: Number(publicity) })));
    }
    function stage(index, updates, events) {
        const row = { ...rowAt(index), ...updates };
        if (events !== undefined) row.events = events;
        const original = quarters[index];
        if (fields.some(([key]) => row[key] !== original[key])
            || eventSignature(row.events) !== eventSignature(original.events)) pending.set(index, row);
        else pending.delete(index);
        updateEditControls();
    }

    function updateEditControls() {
        const toggle = document.getElementById("companyQuarterEditToggle");
        const controls = document.getElementById("companyQuarterEditControls");
        const metric = document.getElementById("companyQuarterMetric");
        toggle.setAttribute("aria-pressed", String(editMode));
        toggle.disabled = chartSaving || !quarters.length;
        document.querySelector(".company-quarter-mode-actions").classList.toggle("d-none", !canEdit);
        toggle.innerHTML = editMode
            ? '<i class="fa-solid fa-eye" aria-hidden="true"></i> Bekijk grafiek'
            : '<i class="fa-solid fa-pen" aria-hidden="true"></i> Grafiek bewerken';
        controls.classList.toggle("d-none", !editMode);
        metric.disabled = chartSaving;
        document.querySelectorAll('input[name="companyQuarterEditMode"]').forEach(input => {
            input.disabled = chartSaving;
            input.checked = input.value === editType;
        });
        document.getElementById("companyQuarterInfluence").disabled = chartSaving || editType !== "drag";
        document.getElementById("companyQuarterInfluenceControls").classList.toggle("d-none", !editMode || editType !== "drag");
        document.getElementById("companyQuarterDragSave").disabled = chartSaving || !pending.size;
        document.getElementById("companyQuarterDragSave").classList.toggle("d-none", !editMode);
        document.querySelector(".company-quarter-mode-actions").classList.toggle("is-view", !editMode);
    }

    function constrainedValue(value, kind, key) {
        const minimum = kind === "score" ? -7 : 0;
        const maximum = kind === "score" ? 7 : key === "issuedShareCount" ? 1000000 : 9999999999.99;
        const clamped = Math.max(minimum, Math.min(maximum, value));
        const factor = kind === "money" ? 100 : 1;
        return Math.sign(clamped) * Math.round(Math.abs(clamped) * factor) / factor;
    }

    function draw(resetScroll = false) {
        const content = document.getElementById("companyQuarterContent");
        const metric = document.getElementById("companyQuarterMetric");
        if (!content || !metric) return;
        const oldChart = content.querySelector(".company-quarter-chart-scroll");
        const scrollFractionX = !resetScroll && oldChart && oldChart.scrollWidth > oldChart.clientWidth
            ? oldChart.scrollLeft / (oldChart.scrollWidth - oldChart.clientWidth) : resetScroll ? 0 : 1;
        const scrollFractionY = !resetScroll && oldChart && oldChart.scrollHeight > oldChart.clientHeight
            ? oldChart.scrollTop / (oldChart.scrollHeight - oldChart.clientHeight) : 0;
        content.replaceChildren();
        paintPreview = null;
        updateEditControls();
        if (!quarters.length) {
            content.appendChild(node("p", "text-muted", "Nog geen kwartalen beschikbaar vanaf de oprichtingsdatum."));
            return;
        }
        firstVisible = Math.max(0, Math.min(startPeriod ?? 0, quarters.length - 1));
        lastVisible = Math.max(firstVisible, Math.min(endPeriod ?? quarters.length - 1, quarters.length - 1));
        const visible = quarters.slice(firstVisible, lastVisible + 1);
        const selected = fields.find(([key]) => key === metric.value) || fields[0];
        const values = visible.map((_, index) => rowAt(firstVisible + index)[selected[0]] === null
            ? null : Number(rowAt(firstVisible + index)[selected[0]] || 0));
        const known = values.filter(value => value !== null);
        const low = known.length ? Math.min(...known) : 0, high = known.length ? Math.max(...known) : 0;
        const margin = Math.max((high - low) * .12, selected[2] === "money" ? 1 : .5);
        const axisLow = editMode && selected[2] === "score" ? -7 : low - margin;
        const axisHigh = editMode && selected[2] === "score" ? 7 : high + margin;
        const spread = axisHigh - axisLow || 1;
        const width = Math.max(690, 112 + (visible.length - 1) * 34 * horizontalZoom + 30);
        const height = Math.round(690 * verticalZoom);
        const plotTop = 39, plotBottom = height - 52;
        const plotLeft = 112, plotRight = width - 30;
        const x = index => plotLeft + index * 34 * horizontalZoom;
        const y = value => plotBottom - (value - axisLow) / spread * (plotBottom - plotTop);
        const scroll = node("div", "company-quarter-chart-scroll");
        scroll.classList.toggle("is-editing", editMode);
        scroll.classList.toggle("is-modal-editing", editMode && editType === "modal");
        const svg = svgNode("svg", { viewBox: `0 0 ${width} ${height}`, width, height,
            role: "img", "aria-label": `${selected[1]} per kwartaal van ${label(visible[0])} tot ${label(visible.at(-1))}` });
        if (known.length) {
            const rough = spread / 5, power = 10 ** Math.floor(Math.log10(rough));
            const factor = rough / power;
            const step = (factor <= 1 ? 1 : factor <= 2 ? 2 : factor <= 5 ? 5 : 10) * power;
            const redZero = selected[0] === "companyValue" && quarters.some(row => Number(row.companyValue) < 0);
            for (let value = Math.ceil(axisLow / step) * step, n = 0; value <= axisHigh + step * .0001 && n < 15; value += step, n++) {
                const tick = Math.abs(value) < step * .0001 ? 0 : value, yValue = y(tick);
                const red = redZero && tick === 0;
                svg.appendChild(svgNode("line", { x1: plotLeft, x2: plotRight, y1: yValue, y2: yValue,
                    stroke: red ? "#aa2020" : "#9d7850", "stroke-width": red ? 2 : 1,
                    "stroke-opacity": red ? ".85" : ".35" }));
                const text = svgNode("text", { x: plotLeft - 8, y: yValue + 4, fill: red ? "#aa2020" : "#402914",
                    "text-anchor": "end", "font-size": 12 });
                text.textContent = amount(tick, selected[2]); svg.appendChild(text);
            }
        }
        visible.forEach((row, index) => {
            if (index === 0 || row.year === visible[index - 1].year) return;
            const boundary = (x(index - 1) + x(index)) / 2;
            svg.appendChild(svgNode("line", { x1: boundary, x2: boundary, y1: plotTop - 9, y2: plotBottom + 10,
                stroke: "#9d7850", "stroke-width": 1, "stroke-opacity": ".55" }));
        });
        const seriesLayer = svgNode("g");
        svg.appendChild(seriesLayer);
        const appendLine = (series) => {
            seriesLayer.replaceChildren();
            let segment = [];
            const appendSegment = () => {
                if (!segment.length) return;
                seriesLayer.appendChild(svgNode("polyline", { points: segment.join(" "), fill: "none", stroke: "#700000",
                    "stroke-width": 3,
                    "stroke-linecap": "round", "stroke-linejoin": "round" }));
                segment = [];
            };
            series.forEach((value, index) => {
                if (value === null) appendSegment();
                else segment.push(`${x(index)},${y(value)}`);
            });
            appendSegment();
        };
        appendLine(values);
        const pointElements = [];
        visible.forEach((row, index) => {
            if (values[index] !== null) {
                const eventCount = canEdit ? row.events?.length || 0 : 0;
                const pendingSnapshot = row.source !== "snapshot" && Boolean(row.pendingSnapshotEvents?.length);
                const pointSize = row.source === "snapshot" ? 8 : 6;
                const point = eventCount && !pendingSnapshot
                    ? svgNode("polygon", { points: `${x(index)},${y(values[index]) - pointSize} ${x(index) - pointSize},${y(values[index]) + pointSize} ${x(index) + pointSize},${y(values[index]) + pointSize}` })
                    : svgNode("circle", { cx: x(index), cy: y(values[index]), r: row.source === "snapshot" ? 7 : 5 });
                point.setAttribute("fill", row.source === "snapshot" ? "#a77837" : pendingSnapshot ? "#858585" : "#700000");
                point.dataset.snapshot = String(row.source === "snapshot");
                point.dataset.pendingSnapshot = String(pendingSnapshot);
                point.setAttribute("role", editMode ? "button" : "img");
                if (editMode && editType === "modal") point.setAttribute("tabindex", "0");
                const title = svgNode("title");
                point.appendChild(title);
                pointElements[index] = { point, title, eventCount };
                point.addEventListener("click", () => { if (editMode && editType === "modal") open(row); });
                point.addEventListener("keydown", event => {
                    if (editMode && editType === "modal" && (event.key === "Enter" || event.key === " ")) { event.preventDefault(); open(row); }
                });
                point.addEventListener("pointerdown", event => {
                    if (!editMode || editType !== "drag" || chartSaving || row.source === "snapshot" || event.button !== 0) return;
                    event.preventDefault();
                    event.stopPropagation();
                    svg.setPointerCapture(event.pointerId);
                    moving = { pointerId: event.pointerId, center: index };
                    dragDraft = { year: row.year, quarter: row.quarter, center: index,
                        value: values[index], radius: Number(document.getElementById("companyQuarterInfluence").value), changed: 0 };
                    paintPreview();
                });
                svg.appendChild(point);
            }
        });
        const years = new Map();
        visible.forEach((row, index) => {
            if (!years.has(row.year)) years.set(row.year, []);
            years.get(row.year).push(index);
        });
        years.forEach((positions, year) => {
            const tick = svgNode("text", { x: (x(positions[0]) + x(positions.at(-1))) / 2,
                y: height - 15, fill: "#402914", "text-anchor": "middle", "font-size": 12 });
            tick.textContent = year; svg.appendChild(tick);
        });
        paintPreview = () => {
            const preview = [...values];
            if (dragDraft && dragDraft.center >= 0) {
                const delta = dragDraft.value - values[dragDraft.center];
                for (const direction of [-1, 1]) {
                    for (let distance = direction === -1 ? 1 : 0; distance <= dragDraft.radius; distance++) {
                        const index = dragDraft.center + direction * distance;
                        if (!visible[index] || visible[index].source === "snapshot" || values[index] === null) break;
                        preview[index] = constrainedValue(values[index] + delta * (1 - distance / (dragDraft.radius + 1)),
                            selected[2], selected[0]);
                    }
                }
                dragDraft.changed = preview.filter((value, index) => value !== values[index]).length;
            }
            appendLine(preview);
            pointElements.forEach((entry, index) => {
                if (!entry) return;
                const { point, title, eventCount } = entry;
                const value = preview[index];
                const size = visible[index].source === "snapshot" ? 8 : 6;
                if (point.tagName.toLowerCase() === "polygon") point.setAttribute("points",
                    `${x(index)},${y(value) - size} ${x(index) - size},${y(value) + size} ${x(index) + size},${y(value) + size}`);
                else point.setAttribute("cy", y(value));
                point.setAttribute("aria-label", `${label(visible[index])}: ${amount(value, selected[2])}. ${editMode
                    ? visible[index].source === "snapshot" ? "Snapshot, niet versleepbaar" : "Sleep om aan te passen"
                    : "Kwartaal bewerken"}`);
                title.textContent = `${label(visible[index])} · ${selected[1]}: ${amount(value, selected[2])} · ${visible[index].source}${eventCount ? ` · ${eventCount} gebeurtenissen` : ""}${visible[index].pendingSnapshotEvents?.length ? ` · Snapshot ontbreekt: ${visible[index].pendingSnapshotEvents.map(event => event.title).join(", ")}` : ""}`;
            });
            updateEditControls();
            if (dragDraft) editStatus(dragDraft.changed
                ? `${label(visible[dragDraft.center])}: ${amount(preview[dragDraft.center], selected[2])}. ${dragDraft.changed} kwartaal${dragDraft.changed === 1 ? "" : "en"} aangepast.`
                : "Sleep een punt omhoog of omlaag. Snapshotpunten blijven vast.");
        };
        let moving = null, panning = null;
        svg.addEventListener("pointermove", event => {
            if (!moving || moving.pointerId !== event.pointerId) return;
            const rect = svg.getBoundingClientRect();
            const position = Math.max(plotTop, Math.min(plotBottom,
                (event.clientY - rect.top) * height / rect.height));
            dragDraft.value = constrainedValue(axisLow + (plotBottom - position) / (plotBottom - plotTop) * spread,
                selected[2], selected[0]);
            paintPreview();
        });
        svg.addEventListener("pointerup", event => {
            if (moving?.pointerId !== event.pointerId) return;
            const delta = dragDraft.value - values[dragDraft.center];
            if (delta) for (const direction of [-1, 1]) {
                for (let distance = direction === -1 ? 1 : 0; distance <= dragDraft.radius; distance++) {
                    const position = dragDraft.center + direction * distance;
                    if (!visible[position] || visible[position].source === "snapshot" || values[position] === null) break;
                    const index = firstVisible + position, current = rowAt(index);
                    const value = constrainedValue(values[position] + delta * (1 - distance / (dragDraft.radius + 1)), selected[2], selected[0]);
                    const update = { [selected[0]]: value };
                    if (selected[0] === "companyValue" && current.shareMarketPrice !== null && Number(current.companyValue) > 0)
                        update.shareMarketPrice = constrainedValue(Number(current.shareMarketPrice) * value / Number(current.companyValue), "money", "shareMarketPrice");
                    stage(index, update);
                }
            }
            moving = null; dragDraft = null;
            editStatus(`${pending.size} kwartaal${pending.size === 1 ? "" : "en"} klaar om te bewaren.`);
            draw();
        });
        svg.addEventListener("pointercancel", event => {
            if (moving?.pointerId !== event.pointerId) return;
            moving = null; dragDraft = null; paintPreview();
        });
        scroll.addEventListener("pointerdown", event => {
            if (editMode || event.pointerType !== "mouse" || event.button !== 0
                || event.target.closest("circle, polygon")) return;
            if (event.clientY >= scroll.getBoundingClientRect().bottom - 18) return;
            panning = { pointerId: event.pointerId, x: event.clientX, y: event.clientY,
                left: scroll.scrollLeft, top: scroll.scrollTop };
            scroll.setPointerCapture(event.pointerId);
            scroll.classList.add("is-panning");
            event.preventDefault();
        });
        scroll.addEventListener("pointermove", event => {
            if (panning?.pointerId === event.pointerId) {
                scroll.scrollLeft = panning.left + panning.x - event.clientX;
                if (verticalZoom > 1 && scroll.scrollHeight > scroll.clientHeight + 2)
                    scroll.scrollTop = panning.top + panning.y - event.clientY;
            }
        });
        const endPan = event => {
            if (panning?.pointerId !== event.pointerId) return;
            panning = null; scroll.classList.remove("is-panning");
        };
        scroll.addEventListener("pointerup", endPan);
        scroll.addEventListener("pointercancel", endPan);
        paintPreview();
        scroll.appendChild(svg); content.appendChild(scroll);
        scroll.scrollLeft = scrollFractionX * Math.max(0, scroll.scrollWidth - scroll.clientWidth);
        scroll.scrollTop = scrollFractionY * Math.max(0, scroll.scrollHeight - scroll.clientHeight);
        if (!known.length) content.appendChild(node("p", "small text-muted mt-2", "Voor deze grafiek zijn nog geen historische waarden bekend."));
        const footer = node("div", "company-quarter-picker mt-3");
        function periodControl(isStart) {
            const wrapper = node("div", "company-quarter-period");
            const group = node("div", "button-group");
            group.appendChild(node("span", "company-quarter-period-label", isStart ? "Van kwartaal" : "Tot kwartaal"));
            const year = node("select", "form-select");
            year.setAttribute("aria-label", isStart ? "Startjaar" : "Eindjaar");
            year.add(new Option("Jaar", ""));
            [...new Set(quarters.map(row => row.year))].forEach(value => year.add(new Option(String(value), String(value))));
            year.value = isStart ? startYear : endYear;
            const quarter = node("select", "form-select");
            quarter.setAttribute("aria-label", isStart ? "Startkwartaal" : "Eindkwartaal");
            quarter.add(new Option("Kwartaal", ""));
            if (year.value) quarters.forEach((row, index) => {
                if (String(row.year) === year.value) quarter.add(new Option(`K${row.quarter}`, String(index)));
            });
            quarter.disabled = !year.value;
            const chosen = isStart ? startPeriod : endPeriod;
            quarter.value = chosen === null ? "" : String(chosen);
            year.addEventListener("change", () => {
                if (isStart) { startYear = year.value; startPeriod = null; }
                else { endYear = year.value; endPeriod = null; }
                draw(true);
            });
            quarter.addEventListener("change", () => {
                const index = quarter.value === "" ? null : Number(quarter.value);
                if (isStart) {
                    startPeriod = index;
                    if (index !== null && endPeriod !== null && index > endPeriod) {
                        endPeriod = null; endYear = "";
                    }
                } else {
                    endPeriod = index;
                    if (index !== null && startPeriod !== null && index < startPeriod) {
                        startPeriod = null; startYear = "";
                    }
                }
                draw(true);
            });
            const clear = node("button", "action-button");
            clear.type = "button";
            clear.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
            clear.setAttribute("aria-label", isStart ? "Beginperiode wissen" : "Eindperiode wissen");
            clear.title = isStart ? "Beginperiode wissen" : "Eindperiode wissen";
            clear.disabled = !year.value && chosen === null;
            clear.addEventListener("click", () => {
                if (isStart) { startYear = ""; startPeriod = null; }
                else { endYear = ""; endPeriod = null; }
                draw(true);
            });
            group.append(year, quarter, clear); wrapper.appendChild(group);
            return wrapper;
        }
        footer.append(periodControl(true), periodControl(false));
        content.appendChild(footer);
        const eventRows = canEdit ? quarters.map((row, index) => rowAt(index)).filter(row => row.events?.length) : [];
        if (eventRows.length) {
            content.appendChild(node("h4", "h5 mt-3 mb-1", "Grote gebeurtenissen"));
            const list = node("div", "company-quarter-events-list");
            const columns = [node("div", "company-quarter-events-column"), node("div", "company-quarter-events-column")];
            eventRows.forEach((row, index) => {
                const card = node("div", "data-card company-quarter-events-card");
                const header = node("div", "d-flex justify-content-between align-items-start gap-2 mb-2");
                header.appendChild(node("h5", "h6 mb-0", `${row.year} K${row.quarter}`));
                const actions = node("div", "button-group");
                const edit = AetherActionButtons.button({ icon: "fa-pen-nib", label: "Edit" });
                edit.setAttribute("aria-label", `${row.year} K${row.quarter} bewerken`);
                edit.title = "Kwartaal bewerken";
                edit.disabled = saving || chartSaving;
                edit.addEventListener("click", () => {
                    editMode = true; editType = "modal";
                    draw();
                    open(row);
                });
                actions.appendChild(edit); header.appendChild(actions); card.appendChild(header);
                row.events.forEach(event => {
                    const description = node("p", "mb-2");
                    description.appendChild(node("strong", "", ["Verhuld", "Beperkt", "Gemengd", "Publiek", "Zeer publiek"][Number(event.publicity) - 1] || "Onbekend"));
                    description.appendChild(document.createTextNode(` – ${event.description}`));
                    card.appendChild(description);
                });
                columns[index < Math.ceil(eventRows.length / 2) ? 0 : 1].appendChild(card);
            });
            list.append(...columns);
            content.appendChild(list);
        }
        if (editMode) content.appendChild(node("p", "small text-muted mt-2 mb-0", editType === "drag"
            ? "Sleep meerdere punten en bewaar ze daarna samen. Gouden snapshotpunten blijven vast."
            : "Klik op een punt om de kwartaalmodal te openen. Bewaar alle wijzigingen daarna samen."));
    }

    function eventEditor(item = {}) {
        const row = node("div", "company-quarter-event row g-2 align-items-end mb-2");
        row.dataset.eventId = String(item.id || 0);
        const textCol = node("div", "col-12 col-md-7");
        const textLabel = node("label", "form-label", "Beschrijving");
        const textInput = node("textarea", "form-control");
        textInput.rows = 2; textInput.maxLength = 2000; textInput.value = item.description || "";
        textLabel.appendChild(textInput); textCol.appendChild(textLabel);
        const scaleCol = node("div", "col-8 col-md-3");
        const scaleLabel = node("label", "form-label", "Zichtbaarheid (1–5)");
        const scale = node("select", "form-select");
        [1, 2, 3, 4, 5].forEach(value => scale.add(new Option(`${value} · ${["verhuld", "beperkt", "gemengd", "publiek", "zeer publiek"][value - 1]}`, value)));
        scale.value = String(item.publicity || 3); scaleLabel.appendChild(scale); scaleCol.appendChild(scaleLabel);
        const removeCol = node("div", "col-4 col-md-2");
        const remove = node("button", "action-button", "Verwijderen"); remove.type = "button";
        remove.addEventListener("click", () => row.remove());
        const group = node("div", "button-group"); group.appendChild(remove); removeCol.appendChild(group);
        row.append(textCol, scaleCol, removeCol);
        return row;
    }

    function updateSaveButtons() {
        const index = quarters.findIndex(row => row.year === active?.year && row.quarter === active?.quarter);
        document.getElementById("companyQuarterSave").disabled = saving;
        document.getElementById("companyQuarterSavePrevious").disabled = saving || index <= 0;
        document.getElementById("companyQuarterSaveNext").disabled = saving || index < 0 || index >= quarters.length - 1;
    }

    function open(row) {
        if (!row || saving || !editMode || editType !== "modal") return;
        const index = quarters.findIndex(item => item.year === row.year && item.quarter === row.quarter);
        if (index >= 0) show(rowAt(index));
    }

    function show(row) {
        active = row;
        updateSaveButtons();
        const title = document.getElementById("companyQuarterModalTitle");
        const body = document.getElementById("companyQuarterModalBody");
        title.textContent = `${label(row)} · bedrijfsevolutie`;
        body.replaceChildren();
        if (row.source === "snapshot") body.appendChild(node("p", "small",
            `De cijfers van de snapshot voor ${row.snapshotEvent} gelden voor dit kwartaal. Bedrijfsgebeurtenissen kun je aanpassen.`));
        else body.appendChild(node("p", "small text-muted",
            "Pas de kwartaalcijfers aan. De wijzigingen worden samen met de andere grafiekbewerkingen bewaard."));
        const grid = node("div", "row g-3");
        fields.forEach(([key, fieldLabel, kind]) => {
            const col = node("div", "col-12 col-sm-6");
            const labelEl = node("label", "form-label", fieldLabel);
            const input = node("input", "form-control");
            input.type = "number"; input.step = kind === "money" ? "0.01" : "1";
            input.min = kind === "score" ? "-7" : "0";
            if (kind === "score") input.max = "7";
            if (key === "issuedShareCount") input.max = "1000000";
            input.value = row[key] === null ? "" : String(row[key]); input.disabled = row.source === "snapshot";
            input.dataset.quarterField = key;
            labelEl.appendChild(input); col.appendChild(labelEl); grid.appendChild(col);
        });
        body.appendChild(grid);
        body.appendChild(node("h4", "h5 mt-3", "Grote gebeurtenissen"));
        const eventHost = node("div", "company-quarter-events");
        row.events.forEach(item => eventHost.appendChild(eventEditor(item)));
        body.appendChild(eventHost);
        const add = node("button", "action-button", "+ Gebeurtenis toevoegen"); add.type = "button";
        add.addEventListener("click", () => eventHost.appendChild(eventEditor()));
        const group = node("div", "button-group mt-2"); group.appendChild(add); body.appendChild(group);
        body.appendChild(node("div", "alert alert-danger d-none mt-3 mb-0 company-quarter-error"));
        bootstrap.Modal.getOrCreateInstance(document.getElementById("companyQuarterModal")).show();
    }

    async function save(direction = 0) {
        if (!active || saving) return;
        const currentIndex = quarters.findIndex(row => row.year === active.year && row.quarter === active.quarter);
        if (direction && (currentIndex < 0 || !quarters[currentIndex + direction])) return;
        const body = document.getElementById("companyQuarterModalBody");
        const error = body.querySelector(".company-quarter-error");
        const payload = { idCompany: companyId, year: active.year, quarter: active.quarter, events: [] };
        for (const [key, , kind] of fields) {
            if (active.source === "snapshot") continue;
            const input = body.querySelector(`[data-quarter-field="${key}"]`);
            if (key === "shareMarketPrice" && !input.value) {
                payload[key] = null;
                continue;
            }
            const value = Number(input.value);
            if (!input.value || !Number.isFinite(value) || (kind !== "money" && !Number.isInteger(value))
                || value < (kind === "score" ? -7 : 0) || (kind === "score" && value > 7)
                || (key === "issuedShareCount" && value > 1000000)) {
                error.textContent = `Controleer ${input.parentElement.firstChild.textContent.toLowerCase()}.`;
                error.classList.remove("d-none"); return;
            }
            payload[key] = value;
        }
        for (const eventRow of body.querySelectorAll(".company-quarter-event")) {
            const description = eventRow.querySelector("textarea").value.trim();
            if (!description) { error.textContent = "Vul bij elke gebeurtenis een beschrijving in."; error.classList.remove("d-none"); return; }
            payload.events.push({ id: Number(eventRow.dataset.eventId || 0), description, publicity: Number(eventRow.querySelector("select").value) });
        }
        stage(currentIndex, payload, payload.events);
        editStatus(`${pending.size} kwartaal${pending.size === 1 ? "" : "en"} klaar om te bewaren.`);
        if (!direction) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById("companyQuarterModal")).hide();
            draw();
        } else show(rowAt(currentIndex + direction));
    }

    async function saveDrag() {
        if (!editMode || !pending.size || chartSaving) return;
        const id = companyId;
        chartSaving = true;
        updateEditControls();
        editStatus("Grafiekbewerking bewaren…");
        try {
            const edits = [...pending.entries()].map(([index, row]) => {
                const edit = { year: row.year, quarter: row.quarter };
                fields.forEach(([key]) => { edit[key] = row[key]; });
                if (eventSignature(row.events) !== eventSignature(quarters[index].events))
                    edit.events = (row.events || []).map(({ id, description, publicity }) => ({ id: id || 0, description, publicity }));
                return edit;
            });
            const result = await apiFetchJson("api/companies/saveCompanyQuarterDrag.php", {
                method: "POST", body: { idCompany: id, edits }
            });
            if (companyId !== id) return;
            quarters = Array.isArray(result?.quarters) ? result.quarters : quarters;
            annotateMissingSnapshots();
            pending.clear();
            dragDraft = null;
            editMode = false;
            editStatus(`${Number(result?.changedQuarters || 0)} kwartaal${Number(result?.changedQuarters) === 1 ? "" : "en"} bewaard.`);
            draw();
        } catch (error) {
            editStatus(error?.message || "Kon de grafiekbewerking niet bewaren.", true);
        } finally {
            chartSaving = false;
            updateEditControls();
        }
    }

    async function render(company) {
        canEdit = company?.canManageCompany !== false;
        const section = document.getElementById("companyQuarterHistory");
        const content = document.getElementById("companyQuarterContent");
        if (!section || !content) return false;
        const nextCompanyId = Number(company?.id || 0);
        if (nextCompanyId !== companyId) {
            quarters = []; editMode = false; dragDraft = null; pending.clear();
            firstVisible = 0; lastVisible = -1;
            startPeriod = null; endPeriod = null; startYear = ""; endYear = "";
            horizontalZoom = 1; verticalZoom = 1;
            document.getElementById("companyQuarterHorizontalZoom").value = "100";
            document.getElementById("companyQuarterVerticalZoom").value = "100";
            document.getElementById("companyQuarterHorizontalZoomValue").value = "100%";
            document.getElementById("companyQuarterVerticalZoomValue").value = "100%";
            editStatus();
        }
        companyId = nextCompanyId;
        snapshotEventOptions = Array.isArray(company?.snapshotEventOptions) ? company.snapshotEventOptions : [];
        existingSnapshotIds = new Set((company?.snapshots || []).map(snapshot => Number(snapshot.idEvent)));
        foundationGameDate = String(company?.foundationSnapshot?.foundationDate || company?.foundationDate || "").slice(0, 10);
        const ticket = ++requestId;
        section.classList.toggle("d-none", !companyId || Boolean(company?.isDraft));
        if (!companyId || company?.isDraft) {
            quarters = []; content.replaceChildren(); updateEditControls(); return false;
        }
        const metric = document.getElementById("companyQuarterMetric");
        const previousMetric = metric.value;
        metric.replaceChildren();
        fields.filter(([, , kind]) => canEdit || kind !== "score").forEach(([key, label]) => metric.add(new Option(label, key)));
        if ([...metric.options].some(option => option.value === previousMetric)) metric.value = previousMetric;
        content.textContent = "Kwartaalhistoriek laden…";
        updateEditControls();
        try {
            const response = await apiFetchJson("api/companies/getCompanyQuarters.php", {
                method: "POST", body: { idCompany: companyId }
            });
            if (ticket !== requestId) return false;
            quarters = Array.isArray(response?.quarters) ? response.quarters : [];
            annotateMissingSnapshots();
            pending.clear(); editMode = false;
            firstVisible = 0; lastVisible = quarters.length - 1;
            startPeriod = null; endPeriod = null; startYear = ""; endYear = "";
            draw();
            return true;
        } catch (error) {
            if (ticket === requestId) content.textContent = error?.message || "Kon de kwartaalhistoriek niet laden.";
            return false;
        }
    }
    document.getElementById("companyQuarterMetric")?.addEventListener("change", () => draw());
    document.getElementById("companyQuarterEditToggle")?.addEventListener("click", () => {
        if (chartSaving || !canEdit) return;
        const discarded = editMode ? pending.size : 0;
        if (editMode) pending.clear();
        editMode = !editMode;
        dragDraft = null;
        editStatus(discarded ? "Niet bewaarde wijzigingen zijn verwijderd." : "");
        draw();
    });
    document.querySelectorAll('input[name="companyQuarterEditMode"]').forEach(input => {
        input.addEventListener("change", event => {
            if (!event.target.checked) return;
            editType = event.target.value;
            draw();
        });
    });
    document.getElementById("companyQuarterInfluence")?.addEventListener("input", event => {
        const radius = Number(event.target.value);
        document.getElementById("companyQuarterInfluenceValue").value = String(radius);
        if (dragDraft) { dragDraft.radius = radius; paintPreview?.(); }
    });
    document.getElementById("companyQuarterDragSave")?.addEventListener("click", () => { void saveDrag(); });
    for (const [axis, name] of [["Horizontal", "horizontal"], ["Vertical", "vertical"]]) {
        document.getElementById(`companyQuarter${axis}Zoom`)?.addEventListener("input", event => {
            const value = Number(event.target.value);
            if (name === "horizontal") horizontalZoom = value / 100;
            else verticalZoom = value / 100;
            document.getElementById(`companyQuarter${axis}ZoomValue`).value = `${value}%`;
            draw();
        });
    }
    document.getElementById("companyQuarterSave")?.addEventListener("click", () => { void save(); });
    document.getElementById("companyQuarterSavePrevious")?.addEventListener("click", () => { void save(-1); });
    document.getElementById("companyQuarterSaveNext")?.addEventListener("click", () => { void save(1); });
    window.AetherCompanyQuarters = {
        render(company) {
            latestRender = render(company);
            return latestRender;
        },
        whenReady: () => latestRender,
        quarterForEvent(dateStart) {
            const date = gameDate(dateStart);
            if (!date) return null;
            const index = quarterIndexForGameDate(date);
            const first = quarters.length ? quarters[0].year * 4 + quarters[0].quarter - 1 : 0;
            const row = quarters[index - first];
            return row && row.year * 4 + row.quarter - 1 === index ? { ...row } : null;
        }
    };
})();
