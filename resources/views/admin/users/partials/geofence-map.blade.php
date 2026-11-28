@php
    $initLat = old('geofence_lat', isset($user) ? $user->geofence_lat : null);
    $initLng = old('geofence_lng', isset($user) ? $user->geofence_lng : null);
    $initRadius = (int) old('geofence_radius_km', isset($user) && $user->geofence_radius_km ? $user->geofence_radius_km : 25);
    $initRadius = max(1, min(500, $initRadius));
@endphp

<div id="geofenceMapContainer" style="margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0;display:{{ $geoActive ? 'block' : 'none' }};">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        <div>
            <div style="font-size:.8rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-map-marked-alt" style="color:#006838;"></i>
                Live Geofence Map
            </div>
            <div style="font-size:.74rem;color:#64748b;">
                Click anywhere on the map or drag the green pin to set the exact allowed sign-in boundary for this officer.
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" id="geoSnapAssignedBtn" class="btn-nis btn-outline-nis btn-sm" style="padding:5px 10px;font-size:.74rem;">
                <i class="fas fa-crosshairs"></i> Snap to Assigned Command
            </button>
            <button type="button" id="geoUseMyLocBtn" class="btn-nis btn-ghost btn-sm" style="padding:5px 10px;font-size:.74rem;">
                <i class="fas fa-location-arrow"></i> Use Current GPS
            </button>
        </div>
    </div>

    <div id="geofenceLiveMap" style="height:300px;width:100%;border-radius:10px;border:1px solid #cbd5e1;overflow:hidden;z-index:1;background:#e2e8f0;"></div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:12px;">
        <div>
            <label for="geofence_lat" style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin-bottom:4px;">Latitude</label>
            <input type="number" step="0.000001" min="-90" max="90" id="geofence_lat" name="geofence_lat" value="{{ $initLat }}" class="auth-input" placeholder="9.057900" style="padding:7px 10px;font-size:.82rem;font-family:monospace;">
        </div>
        <div>
            <label for="geofence_lng" style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin-bottom:4px;">Longitude</label>
            <input type="number" step="0.000001" min="-180" max="180" id="geofence_lng" name="geofence_lng" value="{{ $initLng }}" class="auth-input" placeholder="7.495100" style="padding:7px 10px;font-size:.82rem;font-family:monospace;">
        </div>
        <div>
            <label for="geofence_radius_km" style="display:flex;justify-content:space-between;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin-bottom:4px;">
                <span>Radius (km)</span>
                <span id="geoRadiusReadout" style="color:#006838;">{{ $initRadius }} km</span>
            </label>
            <div style="display:flex;align-items:center;gap:8px;">
                <input type="range" id="geofence_radius_slider" min="1" max="150" value="{{ min(150, $initRadius) }}" style="flex:1;accent-color:#006838;">
                <input type="number" id="geofence_radius_km" name="geofence_radius_km" min="1" max="500" value="{{ $initRadius }}" class="auth-input" style="width:74px;padding:6px 8px;font-size:.82rem;text-align:center;">
            </div>
        </div>
    </div>

    <div id="geoMapSummary" style="margin-top:10px;padding:8px 12px;border-radius:8px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;font-size:.76rem;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <span>
            <i class="fas fa-shield-halved" style="margin-right:5px;"></i>
            <strong id="geoLocationTitle">NIS Service Headquarters (Sauka, Abuja)</strong>
        </span>
        <span id="geoCoordBadge" style="font-family:monospace;font-weight:700;"></span>
    </div>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('geofenceLiveMap');
    const container = document.getElementById('geofenceMapContainer');
    const geoCheckbox = document.getElementById('geolocation_enabled');
    const latInput = document.getElementById('geofence_lat');
    const lngInput = document.getElementById('geofence_lng');
    const radInput = document.getElementById('geofence_radius_km');
    const radSlider = document.getElementById('geofence_radius_slider');
    const radReadout = document.getElementById('geoRadiusReadout');
    const locTitleEl = document.getElementById('geoLocationTitle');
    const coordBadge = document.getElementById('geoCoordBadge');
    const snapBtn = document.getElementById('geoSnapAssignedBtn');
    const gpsBtn = document.getElementById('geoUseMyLocBtn');

    if (!mapEl || !window.L) return;

    // Canonical coordinates for Nigeria's 36 states + FCT, 8 NIS Zones, and Service HQ
    const STATE_COORDS = {
        AB: [5.5320, 7.4860, 'Abia State Command (Umuahia)'],
        AD: [9.2035, 12.4954, 'Adamawa State Command (Yola)'],
        AK: [5.0377, 7.9128, 'Akwa Ibom State Command (Uyo)'],
        AN: [6.2107, 7.0741, 'Anambra State Command (Awka)'],
        BA: [10.3158, 9.8442, 'Bauchi State Command (Bauchi)'],
        BY: [4.9267, 6.2676, 'Bayelsa State Command (Yenagoa)'],
        BE: [7.7337, 8.5214, 'Benue State Command (Makurdi)'],
        BO: [11.8333, 13.1500, 'Borno State Command (Maiduguri)'],
        CR: [4.9757, 8.3417, 'Cross River State Command (Calabar)'],
        DE: [6.2059, 6.6959, 'Delta State Command (Asaba)'],
        EB: [6.3249, 8.1137, 'Ebonyi State Command (Abakaliki)'],
        ED: [6.3350, 5.6037, 'Edo State Command (Benin City)'],
        EK: [7.6211, 5.2215, 'Ekiti State Command (Ado-Ekiti)'],
        EN: [6.4584, 7.5464, 'Enugu State Command (Enugu)'],
        FC: [8.9765, 7.3676, 'NIS Service HQ / FCT Command (Sauka, Abuja)'],
        GO: [10.2897, 11.1673, 'Gombe State Command (Gombe)'],
        IM: [5.4836, 7.0333, 'Imo State Command (Owerri)'],
        JI: [11.7562, 9.3389, 'Jigawa State Command (Dutse)'],
        KD: [10.5105, 7.4165, 'Kaduna State Command (Kaduna)'],
        KN: [12.0022, 8.5920, 'Kano State Command (Kano)'],
        KT: [12.9908, 7.6018, 'Katsina State Command (Katsina)'],
        KE: [12.4539, 4.1975, 'Kebbi State Command (Birnin Kebbi)'],
        KO: [7.8023, 6.7333, 'Kogi State Command (Lokoja)'],
        KW: [8.4966, 4.5421, 'Kwara State Command (Ilorin)'],
        LA: [6.5244, 3.3792, 'Lagos State Command (Ikeja, Lagos)'],
        NA: [8.4939, 8.5153, 'Nasarawa State Command (Lafia)'],
        NI: [9.6139, 6.5569, 'Niger State Command (Minna)'],
        OG: [7.1475, 3.3619, 'Ogun State Command (Abeokuta)'],
        ON: [7.2571, 5.2058, 'Ondo State Command (Akure)'],
        OS: [7.7827, 4.5418, 'Osun State Command (Osogbo)'],
        OY: [7.3775, 3.9470, 'Oyo State Command (Ibadan)'],
        PL: [9.8965, 8.8583, 'Plateau State Command (Jos)'],
        RI: [4.8156, 7.0498, 'Rivers State Command (Port Harcourt)'],
        SO: [13.0059, 5.2476, 'Sokoto State Command (Sokoto)'],
        TA: [8.8937, 11.3596, 'Taraba State Command (Jalingo)'],
        YO: [11.7470, 11.9608, 'Yobe State Command (Damaturu)'],
        ZA: [12.1628, 6.6645, 'Zamfara State Command (Gusau)'],
    };

    const ZONE_COORDS = {
        'ZONE-A': [6.5244, 3.3792, 'Zone A Headquarters (Lagos)'],
        'ZONE-B': [10.5105, 7.4165, 'Zone B Headquarters (Kaduna)'],
        'ZONE-C': [10.3158, 9.8442, 'Zone C Headquarters (Bauchi)'],
        'ZONE-D': [9.6139, 6.5569, 'Zone D Headquarters (Minna)'],
        'ZONE-E': [5.4836, 7.0333, 'Zone E Headquarters (Owerri)'],
        'ZONE-F': [7.3775, 3.9470, 'Zone F Headquarters (Ibadan)'],
        'ZONE-G': [6.3350, 5.6037, 'Zone G Headquarters (Benin City)'],
        'ZONE-H': [7.7337, 8.5214, 'Zone H Headquarters (Makurdi)'],
    };

    const HQ_DEFAULT = [8.9765, 7.3676, 'NIS Service Headquarters (Sauka, Abuja)'];

    function resolveAssignedCoords() {
        const cat = document.getElementById('user_category')?.value || '';
        const stateCode = (document.getElementById('assigned_state_code')?.value || '').toUpperCase().trim();
        const zoneCode = (document.getElementById('assigned_zonal_command_code')?.value || '').toUpperCase().trim();

        if ((cat === 'state_user' || cat === 'desk_admin') && stateCode) {
            const prefix = stateCode.slice(0, 2);
            if (STATE_COORDS[prefix]) return STATE_COORDS[prefix];
            if (stateCode.includes('MMIA') || stateCode.includes('SEME') || stateCode.includes('APAPA')) return STATE_COORDS.LA;
            if (stateCode.includes('NAIA')) return STATE_COORDS.FC;
            if (stateCode.includes('MAKIA')) return STATE_COORDS.KN;
        }
        if ((cat === 'zonal_commander' || cat === 'zonal_officer') && zoneCode && ZONE_COORDS[zoneCode]) {
            return ZONE_COORDS[zoneCode];
        }
        if (zoneCode && ZONE_COORDS[zoneCode]) {
            return ZONE_COORDS[zoneCode];
        }
        return HQ_DEFAULT;
    }

    const startLat = latInput.value !== '' ? parseFloat(latInput.value) : resolveAssignedCoords()[0];
    const startLng = lngInput.value !== '' ? parseFloat(lngInput.value) : resolveAssignedCoords()[1];
    const startRad = Math.max(1, parseInt(radInput.value || '25', 10));

    const map = L.map(mapEl, { scrollWheelZoom: true }).setView([startLat, startLng], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const pinIcon = L.divIcon({
        className: 'nis-geo-pin',
        html: '<div style="width:22px;height:22px;border-radius:50%;background:#006838;border:3px solid #ffffff;box-shadow:0 2px 8px rgba(0,0,0,.35);"></div>',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });

    const marker = L.marker([startLat, startLng], { draggable: true, icon: pinIcon }).addTo(map);
    const circle = L.circle([startLat, startLng], {
        radius: startRad * 1000,
        color: '#006838',
        fillColor: '#16a34a',
        fillOpacity: 0.16,
        weight: 2,
    }).addTo(map);

    function syncUI(lat, lng, radiusKm, labelText, fitMap) {
        const cleanLat = Number(lat).toFixed(6);
        const cleanLng = Number(lng).toFixed(6);
        const cleanRad = Math.max(1, Math.min(500, Math.round(Number(radiusKm) || 25)));

        latInput.value = cleanLat;
        lngInput.value = cleanLng;
        radInput.value = cleanRad;
        if (radSlider) radSlider.value = Math.min(150, cleanRad);
        if (radReadout) radReadout.textContent = cleanRad + ' km';

        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        circle.setRadius(cleanRad * 1000);

        if (labelText && locTitleEl) locTitleEl.textContent = labelText;
        if (coordBadge) coordBadge.textContent = `${cleanLat}, ${cleanLng} • ${cleanRad} km radius`;

        if (fitMap) {
            try {
                map.fitBounds(circle.getBounds(), { padding: [24, 24], maxZoom: 13 });
            } catch (e) {
                map.setView([lat, lng], 10);
            }
        }
    }

    syncUI(startLat, startLng, startRad, resolveAssignedCoords()[2], true);

    map.on('click', (e) => {
        syncUI(e.latlng.lat, e.latlng.lng, radInput.value, 'Custom Pinned Geofence Center', false);
    });

    marker.on('dragend', () => {
        const pos = marker.getLatLng();
        syncUI(pos.lat, pos.lng, radInput.value, 'Custom Pinned Geofence Center', false);
    });

    radSlider?.addEventListener('input', () => {
        syncUI(latInput.value || startLat, lngInput.value || startLng, radSlider.value, null, false);
    });

    radInput?.addEventListener('change', () => {
        syncUI(latInput.value || startLat, lngInput.value || startLng, radInput.value, null, true);
    });

    [latInput, lngInput].forEach((el) => {
        el?.addEventListener('change', () => {
            const la = parseFloat(latInput.value);
            const lo = parseFloat(lngInput.value);
            if (!Number.isNaN(la) && !Number.isNaN(lo)) {
                syncUI(la, lo, radInput.value, 'Custom Coordinates', true);
            }
        });
    });

    function snapToAssigned() {
        const [la, lo, title] = resolveAssignedCoords();
        syncUI(la, lo, radInput.value || 25, title, true);
    }

    snapBtn?.addEventListener('click', snapToAssigned);
    ['user_category', 'assigned_state_code', 'assigned_zonal_command_code'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', snapToAssigned);
    });

    gpsBtn?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by this browser.');
            return;
        }
        gpsBtn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                gpsBtn.disabled = false;
                syncUI(pos.coords.latitude, pos.coords.longitude, radInput.value || 10, 'Current GPS Location', true);
            },
            () => {
                gpsBtn.disabled = false;
                alert('Unable to read GPS location. Click directly on the map to set the geofence.');
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });

    geoCheckbox?.addEventListener('change', () => {
        if (!container) return;
        container.style.display = geoCheckbox.checked ? 'block' : 'none';
        if (geoCheckbox.checked) {
            setTimeout(() => {
                map.invalidateSize();
                map.fitBounds(circle.getBounds(), { padding: [24, 24], maxZoom: 13 });
            }, 60);
        }
    });
});
</script>
@endonce
