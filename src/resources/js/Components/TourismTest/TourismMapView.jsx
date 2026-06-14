import { useEffect } from 'react';
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function createMarkerIcon(label, variant = 'spot') {
    const isCenter = variant === 'center';

    return L.divIcon({
        className: '',
        html: `
            <div style="
                display:flex;
                align-items:center;
                justify-content:center;
                width:${isCenter ? '34px' : '30px'};
                height:${isCenter ? '34px' : '30px'};
                border-radius:9999px;
                background:${isCenter ? '#6b4c3b' : '#fffdf8'};
                color:${isCenter ? '#fffaf2' : '#6b4c3b'};
                border:2px solid ${isCenter ? '#9a7b4f' : '#c4a35a'};
                box-shadow:0 8px 20px rgba(62,44,28,0.25);
                font-size:${isCenter ? '11px' : '12px'};
                font-weight:700;
                font-family:'Noto Sans JP', sans-serif;
            ">${label}</div>
        `,
        iconSize: [isCenter ? 34 : 30, isCenter ? 34 : 30],
        iconAnchor: [isCenter ? 17 : 15, isCenter ? 17 : 15],
    });
}

function FitBounds({ points }) {
    const map = useMap();

    useEffect(() => {
        if (points.length === 0) {
            return;
        }

        const bounds = L.latLngBounds(points.map((point) => [point.lat, point.lng]));
        map.fitBounds(bounds, { padding: [48, 48], maxZoom: 14 });
    }, [map, points]);

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

    const boundsPoints = [
        { lat: center.latitude, lng: center.longitude },
        ...mappableSpots.map((spot) => ({
            lat: spot.latitude,
            lng: spot.longitude,
        })),
    ];

    return (
        <div className="overflow-hidden rounded-3xl border border-[#e2d4bc] shadow-[0_18px_40px_-28px_rgba(62,44,28,0.45)]">
            <MapContainer
                center={mapCenter}
                zoom={13}
                scrollWheelZoom={false}
                className="z-0 h-80 w-full"
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <FitBounds points={boundsPoints} />
                <Marker
                    position={mapCenter}
                    icon={createMarkerIcon('宿', 'center')}
                >
                    <Popup>
                        <span className="font-medium">{locationName}</span>
                    </Popup>
                </Marker>
                {mappableSpots.map((spot) => (
                    <Marker
                        key={spot.name}
                        position={[spot.latitude, spot.longitude]}
                        icon={createMarkerIcon(String(spot.index + 1).padStart(2, '0'))}
                        eventHandlers={{
                            click: () => onSpotSelect?.(spot.index),
                        }}
                        opacity={activeSpotIndex === null || activeSpotIndex === spot.index ? 1 : 0.72}
                    >
                        <Popup>
                            <div className="space-y-1 text-sm">
                                <p className="font-semibold">{spot.name}</p>
                                <p className="text-slate-600">{spot.distance_text}</p>
                            </div>
                        </Popup>
                    </Marker>
                ))}
            </MapContainer>
            <p className="border-t border-[#efe4d2] bg-[#fffdf8] px-4 py-2 text-[10px] text-[#8b7355]">
                地図データ: OpenStreetMap / 座標: Nominatim
            </p>
        </div>
    );
}
