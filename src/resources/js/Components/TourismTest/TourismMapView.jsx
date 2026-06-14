import { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { MapContainer, Marker, TileLayer, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import TourismSpotExpandedOverlay from '@/Components/TourismTest/TourismSpotExpandedOverlay';
import TourismSpotMiniCard from '@/Components/TourismTest/TourismSpotMiniCard';

const CARD_WIDTH = 160;
const CARD_HEIGHT = 132;
const PIN_RADIUS = 15;
const CENTER_RADIUS = 17;
const GAP = 20;
const BOUNDS_PADDING = 12;

const ANGLE_DELTAS = [0, 0.55, -0.55, 1.1, -1.1, 1.65, -1.65, 2.2, -2.2];
const PLACEMENT_DISTANCES = [96, 112, 128, 144, 168];

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

function makeRect(left, top, width, height) {
    return {
        left,
        top,
        right: left + width,
        bottom: top + height,
    };
}

function rectsOverlap(a, b, padding = 10) {
    return !(
        a.right + padding < b.left
        || b.right + padding < a.left
        || a.bottom + padding < b.top
        || b.bottom + padding < a.top
    );
}

function rectOverlapsCircle(rect, x, y, radius, padding = 10) {
    const closestX = Math.max(rect.left, Math.min(x, rect.right));
    const closestY = Math.max(rect.top, Math.min(y, rect.bottom));
    const dx = x - closestX;
    const dy = y - closestY;

    return dx * dx + dy * dy < (radius + padding) ** 2;
}

function rectWithinBounds(rect, width, height, padding = BOUNDS_PADDING) {
    return (
        rect.left >= padding
        && rect.top >= padding
        && rect.right <= width - padding
        && rect.bottom <= height - padding
    );
}

function closestPointOnRect(px, py, rect) {
    const x = Math.max(rect.left, Math.min(px, rect.right));
    const y = Math.max(rect.top, Math.min(py, rect.bottom));

    if (x !== px || y !== py) {
        return { x, y };
    }

    const distances = [
        { x: rect.left, y: py, d: Math.abs(px - rect.left) },
        { x: rect.right, y: py, d: Math.abs(px - rect.right) },
        { x: px, y: rect.top, d: Math.abs(py - rect.top) },
        { x: px, y: rect.bottom, d: Math.abs(py - rect.bottom) },
    ];

    const nearest = distances.sort((a, b) => a.d - b.d)[0];

    return { x: nearest.x, y: nearest.y };
}

function connectorPoints(pinX, pinY, rect) {
    const end = closestPointOnRect(pinX, pinY, rect);
    const dx = end.x - pinX;
    const dy = end.y - pinY;
    const length = Math.hypot(dx, dy) || 1;

    return {
        lineStartX: pinX + (dx / length) * PIN_RADIUS,
        lineStartY: pinY + (dy / length) * PIN_RADIUS,
        lineEndX: end.x,
        lineEndY: end.y,
    };
}

function placeCard(pinX, pinY, centerX, centerY, placedRects, mapSize) {
    const baseAngle = Math.atan2(pinY - centerY, pinX - centerX);

    for (const distance of PLACEMENT_DISTANCES) {
        for (const delta of ANGLE_DELTAS) {
            const angle = baseAngle + delta;
            const cardCenterX = pinX + Math.cos(angle) * distance;
            const cardCenterY = pinY + Math.sin(angle) * distance;
            const left = cardCenterX - CARD_WIDTH / 2;
            const top = cardCenterY - CARD_HEIGHT / 2;
            const rect = makeRect(left, top, CARD_WIDTH, CARD_HEIGHT);

            const overlaps = placedRects.some((placed) => rectsOverlap(rect, placed))
                || rectOverlapsCircle(rect, pinX, pinY, PIN_RADIUS)
                || rectOverlapsCircle(rect, centerX, centerY, CENTER_RADIUS)
                || !rectWithinBounds(rect, mapSize.x, mapSize.y);

            if (!overlaps) {
                return {
                    left,
                    top,
                    rect,
                    ...connectorPoints(pinX, pinY, rect),
                };
            }
        }
    }

    const fallbackLeft = Math.min(
        Math.max(pinX + PIN_RADIUS + GAP, BOUNDS_PADDING),
        mapSize.x - CARD_WIDTH - BOUNDS_PADDING,
    );
    const fallbackTop = Math.min(
        Math.max(pinY - CARD_HEIGHT - PIN_RADIUS - GAP, BOUNDS_PADDING),
        mapSize.y - CARD_HEIGHT - BOUNDS_PADDING,
    );
    const rect = makeRect(fallbackLeft, fallbackTop, CARD_WIDTH, CARD_HEIGHT);

    return {
        left: fallbackLeft,
        top: fallbackTop,
        rect,
        ...connectorPoints(pinX, pinY, rect),
    };
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

function MapSpotOverlay({
    center,
    mappableSpots,
    expandedSpotIndex,
    onSpotClick,
}) {
    const map = useMap();
    const [layout, setLayout] = useState([]);

    const updateLayout = useCallback(() => {
        const mapSize = map.getSize();
        const centerPoint = map.latLngToContainerPoint([
            center.latitude,
            center.longitude,
        ]);
        const placedRects = [];

        const nextLayout = mappableSpots.map((spot) => {
            const pinPoint = map.latLngToContainerPoint([
                spot.latitude,
                spot.longitude,
            ]);
            const placement = placeCard(
                pinPoint.x,
                pinPoint.y,
                centerPoint.x,
                centerPoint.y,
                placedRects,
                mapSize,
            );

            placedRects.push(placement.rect);

            return {
                ...spot,
                pinX: pinPoint.x,
                pinY: pinPoint.y,
                left: placement.left,
                top: placement.top,
                lineStartX: placement.lineStartX,
                lineStartY: placement.lineStartY,
                lineEndX: placement.lineEndX,
                lineEndY: placement.lineEndY,
            };
        });

        setLayout(nextLayout);
    }, [map, center, mappableSpots]);

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
            <svg className="absolute inset-0 h-full w-full overflow-visible">
                {layout.map((spot) => {
                    const isActive =
                        expandedSpotIndex === null
                        || expandedSpotIndex === spot.index;

                    return (
                        <line
                            key={`connector-${spot.name}`}
                            x1={spot.lineStartX}
                            y1={spot.lineStartY}
                            x2={spot.lineEndX}
                            y2={spot.lineEndY}
                            stroke="#c4a35a"
                            strokeWidth="2"
                            strokeDasharray="6 8"
                            opacity={isActive ? 0.75 : 0.3}
                        />
                    );
                })}
            </svg>
            {layout.map((spot) => (
                <div
                    key={spot.name}
                    className="pointer-events-auto absolute"
                    style={{ left: spot.left, top: spot.top, width: CARD_WIDTH }}
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
                            expandedSpotIndex === null
                            || expandedSpotIndex === spot.index
                                ? 1
                                : 0.65
                        }
                    />
                ))}
                <MapSpotOverlay
                    center={center}
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
