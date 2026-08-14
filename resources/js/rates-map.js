/**
 * The map view of /rates.
 *
 * Leaflet and its stylesheet are imported dynamically so neither reaches the
 * bundle every other page pays for - the map is one view of one page, and the
 * list is the default. Vite emits them as their own chunk, fetched the first
 * time someone actually switches to the map.
 *
 * Tiles come from OpenStreetMap: no key, no billing, and the attribution below
 * is a licence condition rather than a courtesy.
 */
export async function renderRatesMap(element, points, labels) {
    if (!element || !points.length) {
        return;
    }

    const [{ default: L }] = await Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
    ]);

    // The panel is morphed on every filter change, which can hand this the
    // same element twice. Leaflet throws on a container it has already
    // initialised, so the previous instance is torn down first.
    if (element._ratesMap) {
        element._ratesMap.remove();
    }

    const map = L.map(element, { scrollWheelZoom: false });
    element._ratesMap = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const markers = points.map((point) => {
        // A div marker rather than Leaflet's default pin: the rate is the
        // thing worth reading at a glance, and the default pin would need its
        // own image assets resolved through the bundler to say nothing.
        const icon = L.divIcon({
            className: '',
            html:
                `<span class="flex h-full w-full flex-col items-center justify-end">
                    <span class="rounded-full border px-2 py-1 text-xs font-semibold tabular-nums shadow-sm ${
                        point.best
                            ? 'border-primary bg-primary text-white'
                            : 'border-placeholder bg-white text-ink'
                    }">${point.rate}</span>
                    <span class="h-2 w-px ${point.best ? 'bg-primary' : 'bg-border-muted'}"></span>
                </span>`,
            // A real size, not [0, 0]: Leaflet sizes the marker element from
            // this, and a zero-sized marker cannot be clicked or tabbed to -
            // the label renders and nothing happens when you press it.
            iconSize: [72, 34],
            iconAnchor: [36, 34],
            popupAnchor: [0, -34],
        });

        return L.marker([point.lat, point.lng], {
            icon,
            title: point.name,
            // Yerevan holds most of the branches, so labels overlap at the
            // opening zoom. Hovering lifts one clear of its neighbours, and the
            // best rate sits above all of them - it is the one worth finding
            // without hunting.
            riseOnHover: true,
            zIndexOffset: point.best ? 1000 : 0,
        })
            .bindPopup(popup(point, labels))
            .addTo(map);
    });

    map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [40, 40], maxZoom: 15 });
}

function escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));
}

/**
 * Open, shut, or unknown - three states, coloured only for the two we can
 * actually vouch for, and always spelled out in words as well.
 */
function openLine(point) {
    if (point.open === null || point.open === undefined) {
        return `<p class="mt-1 text-xs text-muted">${escape(point.openLabel)}</p>`;
    }

    const tone = point.open ? 'text-primary' : 'text-accent-red';
    const hours = point.hours ? ` <span class="text-muted">${escape(point.hours)}</span>` : '';

    return `<p class="mt-1 text-xs"><span class="font-semibold ${tone}">${escape(point.openLabel)}</span>${hours}</p>`;
}

/**
 * Everything the row would have told them, plus the two things only a map can:
 * where it is, and how to get there.
 */
function popup(point, labels) {
    const line = (label, value) =>
        value ? `<p class="mt-1 text-xs text-muted">${escape(label)} ${escape(value)}</p>` : '';

    return `
        <p class="font-semibold text-ink">${escape(point.name)}</p>
        <p class="text-xs text-muted">${escape(point.branch)}</p>
        ${line('', point.address)}
        <p class="mt-2 tabular-nums text-ink">
            <span class="font-semibold">${escape(point.rate)}</span>
            <span class="text-xs text-muted">${escape(labels.rate)}</span>
        </p>
        ${point.total ? `<p class="mt-1 tabular-nums text-ink"><span class="font-semibold">${escape(point.total)}</span> <span class="text-xs text-muted">${escape(labels.total)}</span></p>` : ''}
        ${line(labels.distance, point.distance)}
        ${openLine(point)}
        <p class="mt-2 flex flex-wrap gap-3">
            <a href="${escape(point.directions)}" target="_blank" rel="noopener noreferrer" class="text-xs font-medium text-primary underline">${escape(labels.directions)}</a>
            ${point.negotiate ? `<a href="${escape(point.negotiate)}" class="text-xs font-medium text-primary underline">${escape(labels.negotiate)}</a>` : ''}
        </p>
    `;
}
