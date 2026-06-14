export default function TourismSpotMiniCard({
    spot,
    index,
    onClick,
    active = false,
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`w-40 overflow-hidden rounded-2xl border bg-[#fffdf8] text-left shadow-[0_12px_28px_-16px_rgba(62,44,28,0.55)] transition hover:-translate-y-0.5 ${
                active
                    ? 'border-[#9a7b4f] ring-2 ring-[#c4a35a]/50'
                    : 'border-[#e2d4bc]'
            }`}
        >
            <div className="relative h-20 overflow-hidden bg-[#ebe2d4]">
                {spot.image_url ? (
                    <img
                        src={spot.image_url}
                        alt={spot.name}
                        className="h-full w-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center text-[10px] tracking-[0.2em] text-[#9a7b4f]">
                        風景
                    </div>
                )}
                <span className="absolute left-2 top-2 rounded-full bg-[#6b4c3b] px-2 py-0.5 text-[10px] font-bold text-[#fffaf2]">
                    {String(index).padStart(2, '0')}
                </span>
            </div>
            <div className="space-y-1 p-3">
                <p className="truncate text-xs font-semibold text-[#2c2419]">{spot.name}</p>
                <p className="text-[10px] text-[#8b7355]">{spot.distance_text}</p>
            </div>
        </button>
    );
}
