import { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { MapContainer, Marker, Polyline, TileLayer, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import TourismSpotExpandedOverlay from '@/Components/TourismTest/TourismSpotExpandedOverlay';
import TourismSpotMiniCard from '@/Components/TourismTest/TourismSpotMiniCard';

const MINI_CARD_OFFSETS = [
    { x: 18, y: -150 },
    { x: -170, y: -60 },
    { x: 18, y: 24 },
];

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

function createSpotMarkerIcon(index) {
    const number = String(index).padStart(2, '0');

    return L.divIcon({
        className: '',
        html: `
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
        `,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

function lockMapInteractions(map) {
    map.dragging.disable();
    map.touchZoom.disable();
    map.doubleClickZoom.disable();
    map.scrollWheelZoom.disable();
    map.boxZoom.disable();
    map.keyboard.disable();

    if (map.zoomControl) {
        map.removeControl(map.zoomControl);
    }
}

function FitMapBounds({ center, mappableSpots }) {
    const map = useMap();

    useEffect(() => {
        map.invalidateSize();
        lockMapInteractions(map);

        const latLngs = [
            [center.latitude, center.longitude],
            ...mappableSpots.map((spot) => [spot.latitude, spot.longitude]),
        ];

        if (latLngs.length === 1) {
            map.setView(latLngs[0], 14, { animate: false });
        } else {
            map.fitBounds(L.latLngBounds(latLngs), {
                padding: [120, 120],
                maxZoom: 15,
                animate: false,
            });
        }
    }, [map, center, mappableSpots]);

    return null;
}

function MapSpotMiniCards({
    mappableSpots,
    expandedSpotIndex,
    onSpotClick,
}) {
    const map = useMap();
    const [layout, setLayout] = useState([]);

    const updateLayout = useCallback(() => {
        setLayout(
            mappableSpots.map((spot) => {
                const point = map.latLngToContainerPoint([
                    spot.latitude,
                    spot.longitude,
                ]);
                const offset = MINI_CARD_OFFSETS[spot.index % MINI_CARD_OFFSETS.length];

                return {
                    ...spot,
                    left: point.x + offset.x,
                    top: point.y + offset.y,
                };
            }),
        );
    }, [map, mappableSpots]);

    useEffect(() => {
        updateLayout();

        const handleMapChange = () => updateLayout();
        map.on('moveend', handleMapChange);
        map.on('zoomend', handleMapChange);
        map.on('resize', handleMapChange);

        const timer = window.setTimeout(() => {
            map.invalidateSize();
            updateLayout();
        }, 200);

        return () => {
            map.off('moveend', handleMapChange);
            map.off('zoomend', handleMapChange);
            map.off('resize', handleMapChange);
            window.clearTimeout(timer);
        };
    }, [map, updateLayout]);

    return createPortal(
        <div className="pointer-events-none absolute inset-0 z-[700]">
            {layout.map((spot) => (
                <div
                    key={spot.name}
                    className="pointer-events-auto absolute -translate-y-1/2"
                    style={{ left: spot.left, top: spot.top }}
                >
                    <TourismSpotMiniCard
                        spot={spot}
                        index={spot.index + 1}
                        active={expandedSpotIndex === spot.index}
                        onClick={() => onSpotClick(spot.index)}
                    />
                </div>
            ))}
        </div>,
        map.getContainer(),
    );
}

function hasCoordinates(latitude, longitude) {
    return Number.isFinite(latitude) && Number.isFinite(longitude);
}

export default function TourismMapView({
    center,
    locationName,
    spots,
}) {
    const [expandedSpotIndex, setExpandedSpotIndex] = useState(null);

    if (!hasCoordinates(center?.latitude, center?.longitude)) {
        return (
            <div className="mx-auto flex h-[100vh] w-[100vh] max-w-full items-center justify-center rounded-3xl border border-dashed border-[#d9cdb8] bg-[#fffdf8]/70 text-sm text-[#8b7355]">
                地図を表示する座標がありません
            </div>
        );
    }

    const mapCenter = [center.latitude, center.longitude];
    const mappableSpots = spots
        .map((spot, index) => ({ ...spot, index }))
        .filter((spot) => hasCoordinates(spot.latitude, spot.longitude));
    const missingSpotCount = spots.length - mappableSpots.length;
    const expandedSpot =
        expandedSpotIndex === null ? null : spots[expandedSpotIndex] ?? null;

    return (
        <div className="relative mx-auto h-[100vh] w-[100vh] max-w-full overflow-hidden rounded-3xl border border-[#e2d4bc] shadow-[0_18px_40px_-28px_rgba(62,44,28,0.45)]">
            <MapContainer
                center={mapCenter}
                zoom={13}
                scrollWheelZoom={false}
                dragging={false}
                touchZoom={false}
                doubleClickZoom={false}
                boxZoom={false}
                keyboard={false}
                zoomControl={false}
                className="z-0 h-full w-full"
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <FitMapBounds center={center} mappableSpots={mappableSpots} />
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
                            opacity:
                                expandedSpotIndex === null ||
                                expandedSpotIndex === spot.index
                                    ? 0.55
                                    : 0.25,
                            dashArray: '6 8',
                        }}
                    />
                ))}
                <Marker position={mapCenter} icon={createCenterMarkerIcon()} />
                {mappableSpots.map((spot) => (
                    <Marker
                        key={`pin-${spot.name}`}
                        position={[spot.latitude, spot.longitude]}
                        icon={createSpotMarkerIcon(spot.index + 1)}
                        eventHandlers={{
                            click: () => setExpandedSpotIndex(spot.index),
                        }}
                        opacity={
                            expandedSpotIndex === null ||
                            expandedSpotIndex === spot.index
                                ? 1
                                : 0.65
                        }
                    />
                ))}
                <MapSpotMiniCards
                    mappableSpots={mappableSpots}
                    expandedSpotIndex={expandedSpotIndex}
                    onSpotClick={setExpandedSpotIndex}
                />
            </MapContainer>

            <TourismSpotExpandedOverlay
                spot={expandedSpot}
                locationName={locationName}
                index={expandedSpotIndex === null ? null : expandedSpotIndex + 1}
                onClose={() => setExpandedSpotIndex(null)}
            />

            <div className="pointer-events-none absolute bottom-0 left-0 right-0 z-[750] border-t border-[#efe4d2] bg-[#fffdf8]/90 px-4 py-2 text-[10px] text-[#8b7355] backdrop-blur-sm">
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
