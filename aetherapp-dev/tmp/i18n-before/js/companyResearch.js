// Character-specific company information. Unknown events never reach participant browsers.
const aetherCompanyResearch = (() => {
    const node = (tag, css = '', text) => {
        const el = document.createElement(tag); el.className = css;
        if (text !== undefined) el.textContent = text;
        return el;
    };
    const money = value => value == null ? 'Onbekend' : aetherShareUi.money(value);
    const drafts = new Map();
    const zoomStates = new Map();
    function fact(host, label, value) {
        const row = node('div', 'data-card-fact');
        row.append(node('span', 'data-card-fact-label', label), node('span', '', value)); host.append(row);
    }
    function chart(host, rows, key) {
        host.append(node('h4', 'h5', 'Bedrijfsevolutie per kwartaal'));
        if (!rows.length) { host.append(node('p', 'text-muted', 'Geen kwartaalgegevens beschikbaar.')); return; }
        const ns = 'http://www.w3.org/2000/svg';
        const svgNode = (tag, attrs = {}, text) => {
            const el = document.createElementNS(ns, tag);
            Object.entries(attrs).forEach(([key, value]) => el.setAttribute(key, value));
            if (text !== undefined) el.textContent = text;
            return el;
        };
        const zoom = zoomStates.get(key) || {horizontal: 1, vertical: 1};
        zoomStates.set(key, zoom);
        const layout = node('div', 'company-quarter-graph-layout');
        const scroll = node('div', 'company-quarter-chart-scroll company-research-chart');
        const controls = node('div', 'company-quarter-zoom-controls');
        controls.setAttribute('aria-label', 'Grafiekzoom');
        layout.append(scroll, controls); host.append(layout);
        const draw = (initial = false) => {
            const fractionX = !initial && scroll.scrollWidth > scroll.clientWidth ? scroll.scrollLeft / (scroll.scrollWidth - scroll.clientWidth) : 1;
            const fractionY = scroll.scrollHeight > scroll.clientHeight ? scroll.scrollTop / (scroll.scrollHeight - scroll.clientHeight) : 0;
            const width = Math.max(690, 140 + Math.max(0, rows.length - 1) * 34 * zoom.horizontal);
            const height = Math.round(690 * zoom.vertical), left = 110, right = width - 25;
            const bottom = height - 52;
            const values = rows.map(row => Number(row.companyValue));
            const low = Math.min(...values), high = Math.max(...values);
            const raw = Math.max((high - low) / 4, Math.abs(high) * .01, 1);
            const power = 10 ** Math.floor(Math.log10(raw));
            const step = [1, 2, 5, 10].map(n => n * power).find(n => n >= raw);
            const min = Math.floor(low / step) * step, max = Math.max(min + step, Math.ceil(high / step) * step);
            const x = i => left + i * 34 * zoom.horizontal;
            const y = value => bottom - (value - min) / (max - min) * (bottom - 39);
            const svg = svgNode('svg', {viewBox: `0 0 ${width} ${height}`, width, height, role: 'img', 'aria-label': 'Bedrijfswaarde per kwartaal'});
            for (let tick = min; tick <= max + step / 10; tick += step) {
                const color = tick === 0 && min < 0 ? '#a00000' : '#a38153';
                svg.append(svgNode('line', {x1: left, x2: right, y1: y(tick), y2: y(tick), stroke: color, 'stroke-width': .6}),
                    svgNode('text', {x: left - 8, y: y(tick) + 4, 'text-anchor': 'end', fill: '#402914', 'font-size': 12}, new Intl.NumberFormat('nl-BE').format(tick)));
            }
            rows.forEach((row, index) => {
                if (index === 0 || row.year !== rows[index - 1].year) svg.append(
                    svgNode('line', {x1: x(index), x2: x(index), y1: 15, y2: bottom + 10, stroke: '#a38153', 'stroke-width': .5}),
                    svgNode('text', {x: x(index), y: height - 20, 'text-anchor': 'middle', fill: '#402914', 'font-size': 12}, row.year));
            });
            svg.append(svgNode('polyline', {points: values.map((value, i) => `${x(i)},${y(value)}`).join(' '), fill: 'none', stroke: '#700000', 'stroke-width': 2.5}));
            rows.forEach((row, i) => {
                const dot = svgNode('circle', {cx: x(i), cy: y(values[i]), r: 3.5, fill: '#700000'});
                dot.append(svgNode('title', {}, `${row.year} · K${row.quarter}: ${money(row.companyValue)}`)); svg.append(dot);
            });
            scroll.replaceChildren(svg);
            scroll.scrollLeft = fractionX * Math.max(0, (scroll.scrollWidth || 0) - (scroll.clientWidth || 0));
            scroll.scrollTop = fractionY * Math.max(0, (scroll.scrollHeight || 0) - (scroll.clientHeight || 0));
            };
        for (const [axis, label, icon, min, max, step] of [
            ['horizontal', 'Horizontale zoom', 'fa-arrows-left-right', 25, 300, 5],
            ['vertical', 'Verticale zoom', 'fa-arrows-up-down', 100, 400, 10]
        ]) {
            const wrap = node('label'); wrap.title = label;
            const slider = node('input'); slider.type = 'range'; slider.min = String(min); slider.max = String(max); slider.step = String(step);
            slider.value = String(zoom[axis] * 100); slider.setAttribute('aria-label', label);
            const output = node('output', '', `${slider.value}%`);
            slider.addEventListener('input', () => {
                zoom[axis] = Math.max(min, Math.min(max, Number(slider.value))) / 100;
                output.textContent = `${Math.round(zoom[axis] * 100)}%`; draw();
            });
            wrap.append(node('i', `fa-solid ${icon}`), slider, output); controls.append(wrap);
        }
        let drag = null;
        scroll.addEventListener('pointerdown', event => {
            if (event.button !== 0 || event.target.closest?.('circle')) return;
            drag = {x: event.clientX, y: event.clientY, left: scroll.scrollLeft, top: scroll.scrollTop};
            scroll.setPointerCapture?.(event.pointerId); event.preventDefault();
        });
        scroll.addEventListener('pointermove', event => {
            if (!drag) return;
            scroll.scrollLeft = drag.left - (event.clientX - drag.x);
            if (zoom.vertical > 1) scroll.scrollTop = drag.top - (event.clientY - drag.y);
        });
        scroll.addEventListener('pointerup', () => { drag = null; });
        scroll.addEventListener('pointercancel', () => { drag = null; });
        draw(true);
    }
    async function mount(host, {characterId, companyId, eventId, shareContent, backButton}) {
        const key = `${characterId}:${companyId}:${eventId}`;
        host.textContent = 'Bedrijfsinformatie laden…';
        try {
            const data = await apiFetchJson(`api/shares/getCompanyResearch.php?idCharacter=${characterId}&idCompany=${companyId}&idEvent=${eventId}`);
            if (!host.isConnected) return;
            const render = () => {
                host.replaceChildren();
                const company = data.company;
                const panel = node('section', 'company-research-details mb-4');
                const content = node('div');
                const header = node('div', 'company-research-heading');
                if (company.logoUrl) { const image = node('img', 'company-research-logo'); image.src = company.logoUrl; image.alt = ''; header.append(image); }
                header.append(node('h3', 'h4', company.companyName));
                if (backButton) { const actions = AetherActionButtons.group([backButton]); actions.className += ' company-research-back'; header.append(actions); }
                content.append(header);
                const facts = node('div', 'data-card-facts company-research-facts my-3');
                fact(facts, 'Oprichtingsdatum', company.foundationDate || 'Onbekend');
                fact(facts, 'Type', company.companyTypeLabel || 'Onbekend');
                fact(facts, 'Bedrijfswaarde', money(company.companyValue));
                fact(facts, 'Uitgegeven aandelen', company.issuedShareCount == null ? 'Onbekend' : String(company.issuedShareCount));
                fact(facts, 'Marktprijs per aandeel', money(company.shareMarketPrice));
                fact(facts, 'Beursgenoteerd', Number(company.isShareListed) === 1 ? 'Ja' : 'Nee');
                content.append(facts);
                const description = node('div', 'company-description-display');
                // The dedicated endpoint sanitizes this rich text with the company allowlist.
                description.innerHTML = company.description || '';
                content.append(description); panel.append(content); host.append(panel);
                const research = node('section', 'mb-4');
                const body = node('div');
                body.append(node('p', 'text-muted', `Informatie bij ${data.eventTitle}`));
                if (shareContent) body.append(shareContent);
                research.append(body); host.append(research);
                chart(body, data.quarters || [], key);
                const events = data.events || [];
                const head = node('div', 'd-flex justify-content-between align-items-start gap-2 mt-4 mb-3');
                head.append(node('h4', 'h5', 'Belangrijke gebeurtenissen'));
                const draft = drafts.get(key);
                const controls = [];
                const edit = AetherActionButtons.button({icon: 'fa-pen-nib', label: 'Edit', onClick: () => {
                    drafts.set(key, new Set(events.filter(event => event.isKnown).map(event => Number(event.id)))); render();
                }});
                if (data.canEdit && !draft) controls.push(edit);
                const status = node('p', 'small mt-2'); status.setAttribute('role', 'status');
                if (draft) {
                    const save = AetherActionButtons.button({icon: 'fa-check', label: 'Save', onClick: async () => {
                        save.disabled = true;
                        try {
                            await apiFetchIdempotentJson('api/shares/saveCompanyResearch.php', {method: 'POST', body: {
                                idCharacter: characterId, idCompany: companyId, idEvent: eventId,
                                revision: data.revision, knownEventIds: [...draft].sort((a, b) => a - b)
                            }});
                            drafts.delete(key); await mount(host, {characterId, companyId, eventId, shareContent, backButton});
                        } catch (error) { status.textContent = aetherShareUi.errorMessage(error); save.disabled = false; }
                    }});
                    const cancel = AetherActionButtons.button({icon: 'fa-xmark', label: 'Cancel', onClick: () => { drafts.delete(key); render(); }});
                    controls.push(save, cancel);
                }
                if (controls.length) head.append(AetherActionButtons.group(controls)); body.append(head);
                if (!data.researchAvailable) body.append(node('p', 'text-muted', 'De kennis van bedrijfsgebeurtenissen is beschikbaar na migratie 0022.'));
                const visible = events.filter(event => draft || event.isKnown);
                if (!visible.length) body.append(node('p', 'text-muted', 'Geen belangrijke gebeurtenissen bekend.'));
                const list = node('div', 'company-research-events');
                visible.forEach(event => {
                    const card = node('section', 'data-card company-research-event-group mb-3');
                    card.append(node('h5', 'h6', `${event.year} · K${event.quarter}`));
                        const row = node(draft ? 'label' : 'p', 'd-block mb-2');
                        if (draft) {
                            const check = node('input', 'form-check-input me-2'); check.type = 'checkbox'; check.checked = draft.has(Number(event.id));
                            check.addEventListener('change', () => check.checked ? draft.add(Number(event.id)) : draft.delete(Number(event.id))); row.append(check);
                        }
                        if (data.canViewVisibility ?? data.canEdit) row.append(node('strong', '', `Zichtbaarheid ${event.publicity}`), document.createTextNode(' – '));
                        row.append(document.createTextNode(event.description)); card.append(row);
                    list.append(card);
                });
                body.append(list, status);
            };
            render();
        } catch (error) { if (host.isConnected) host.textContent = aetherShareUi.errorMessage(error); }
    }
    return {mount, hasDraft: () => drafts.size > 0, reset: () => drafts.clear()};
})();
