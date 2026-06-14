import TourismSpotCard from '@/Components/TourismTest/TourismSpotCard';

export default function TourismSpotExpandedOverlay({
    spot,
    locationName,
    index,
    onClose,
}) {
    if (!spot) {
        return null;
    }

    return (
        <div className="absolute inset-0 z-[800] flex items-center justify-center bg-[#2c2419]/45 p-4 backdrop-blur-[2px]">
            <div className="relative w-full max-w-sm">
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="閉じる"
                    className="absolute -right-2 -top-2 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-[#e2d4bc] bg-[#fffdf8] text-lg leading-none text-[#6b4c3b] shadow-md"
                >
                    ×
                </button>
                <TourismSpotCard spot={spot} locationName={locationName} index={index} highlighted />
            </div>
        </div>
    );
}
