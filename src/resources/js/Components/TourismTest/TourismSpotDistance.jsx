export default function TourismSpotDistance({ distanceText, distanceKm, locationName }) {
    const label = distanceText || (distanceKm != null ? `約 ${distanceKm}km` : '距離不明');

    return (
        <div className="inline-flex items-center gap-2 rounded-full border border-[#e2d4bc] bg-[#faf4ea] px-3 py-1.5 text-xs text-[#6b4c3b]">
            <span aria-hidden="true" className="text-[#c4a35a]">
                ◆
            </span>
            <span>
                <span className="font-medium">{locationName}</span>
                <span className="text-[#8b7355]"> から </span>
                <span className="font-semibold tracking-wide">{label}</span>
            </span>
        </div>
    );
}
