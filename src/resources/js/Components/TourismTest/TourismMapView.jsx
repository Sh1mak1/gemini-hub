import { useEffect } from 'react';
import { MapContainer, Marker, Polyline, TileLayer, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const FIXED_ZOOM = 13;

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function createCenterMarkerIcon() {
    return L.divIcon({
        className: '',
        html: `
            <div style="
                display:flex;
                align-items:center;
                justify-content:center;
                width:34px;
                height:34px;
                border-radius:9999px;
                background:#6b4c3b;
                color:#fffaf2;
                border:2px solid #9a7b4f;
                box-shadow:0 8px 20px rgba(62,44,28,0.25);
                font-size:11px;
                font-weight:700;
                font-family:'Noto Sans JP', sans-serif;
            ">宿</div>
        `,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
    });
}

function createSpotMarkerIcon(index, name) {
    const label = escapeHtml(name);
    const number = String(index).padStart(2, '0');

    return L.divIcon({
        className: '',
        html: `
            <div style="
                display:flex;
                flex-direction:column;
                align-items:center;
                gap:4px;
                width:120px;
                margin-left:-60px;
                margin-top:-30px;
                pointer-events:auto;
            ">
                <div style="
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    width:30px;
                    height:30px;
                    border-radius:9999px;
                    background:#fffdf8;
                    color:#6b4c3b;
                    border:2px solid #c4a35a;
                    box-shadow:0 8px 20px rgba(62,44,28,0.25);
                    font-size:12px;
                    font-weight:700;
                    font-family:'Noto Sans JP', sans-serif;
                ">${number}</div>
                <div style="
                    max-width:120px;
                    rounded:9999px;
                    background:rgba(255,253,248,0.95);
                    color:#2c2419;
                    border:1px solid #e2d4bc;
                    box-shadow:0 6px 16px rgba(62,44,28,0.18);
                    padding:4px 8px;
                    font-size:10px;
                    font-weight:600;
                    line-height:1.3;
                    text-align:center;
                    white-space:nowrap;
                    overflow:hidden;
                    text-overflow:ellipsis;
                    font-family:'Noto Sans JP', sans-serif;
                ">${label}</div>
            </div>
        `,
        iconSize: [120, 50],
        iconAnchor: [60, 30],
    });
}

function FixedMapView({ center }) {
    const map = useMap();

    useEffect(() => {
        map.setView(center, FIXED_ZOOM, { animate: false });
        map.dragging.disable();
        map.touchZoom.disable();
        map.doubleClickZoom.disable();
        map.scrollWheelZoom.disable();
        map.boxZoom.disable();
        map.keyboard.disable();
        if (map.zoomControl) {
            map.removeControl(map.zoomControl);
        }
    }, [map, center]);

    return null;
}

function hasCoordinates(latitude, longitude) {
    return Number.isFinite(latitude) && Number.isFinite(longitude);
}

export default function TourismMapView({
    center,
    locationName,
    spots,
    activeSpotIndex = null,
    onSpotSelect,
}) {
    if (!hasCoordinates(center?.latitude, center?.longitude)) {
        return (
            <div className="flex h-80 items-center justify-center rounded-3xl border border-dashed border-[#d9cdb8] bg-[#fffdf8]/70 text-sm text-[#8b7355]">
                地図を表示する座標がありません
            </div>
        );
    }

    const mapCenter = [center.latitude, center.longitude];
    const mappableSpots = spots
        .map((spot, index) => ({ ...spot, index }))
        .filter((spot) => hasCoordinates(spot.latitude, spot.longitude));
    const missingSpotCount = spots.length - mappableSpots.length;

    return (
        <div className="overflow-hidden rounded-3xl border border-[#e2d4bc] shadow-[0_18px_40px_-28px_rgba(62,44,28,0.45)]">
            <MapContainer
                center={mapCenter}
                zoom={FIXED_ZOOM}
                scrollWheelZoom={false}
                dragging={false}
                touchZoom={false}
                doubleClickZoom={false}
                boxZoom={false}
                keyboard={false}
                zoomControl={false}
                className="z-0 h-80 w-full"
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <FixedMapView center={mapCenter} />
                {mappableSpots.map((spot) => (
                    <Polyline
                        key={`line-${spot.name}`}
                        positions={[
                            mapCenter,
                            [spot.latitude, spot.longitude],
                        ]}
                        pathOptions={{
                            color: '#c4a35a',
                            weight: 2,
                            opacity: activeSpotIndex === null || activeSpotIndex === spot.index ? 0.55 : 0.25,
                            dashArray: '6 8',
                        }}
                    />
                ))}
                <Marker position={mapCenter} icon={createCenterMarkerIcon()} />
                {mappableSpots.map((spot) => (
                    <Marker
                        key={spot.name}
                        position={[spot.latitude, spot.longitude]}
                        icon={createSpotMarkerIcon(spot.index + 1, spot.name)}
                        eventHandlers={{
                            click: () => onSpotSelect?.(spot.index),
                        }}
                        opacity={activeSpotIndex === null || activeSpotIndex === spot.index ? 1 : 0.65}
                    />
                ))}
            </MapContainer>
            <div className="border-t border-[#efe4d2] bg-[#fffdf8] px-4 py-2 text-[10px] text-[#8b7355]">
                <p>
                    <span className="font-medium text-[#6b4c3b]">{locationName}</span>
                    を中心に、おすすめの三箇所を表示しています
                </p>
                {missingSpotCount > 0 && (
                    <p className="mt-1">
                        {missingSpotCount} 件は座標を取得できなかったため地図に表示されていません
                    </p>
                )}
                <p className="mt-1">地図データ: OpenStreetMap / 座標: Nominatim</p>
            </div>
        </div>
    );
}
