export default function TourismSpotDistance({ distanceText, distanceKm, locationName }) {
    const label = distanceText || (distanceKm != null ? `約 ${distanceKm}km` : '距離不明');

    return (
        <p className="text-sm text-indigo-700 dark:text-indigo-300">
            <span className="font-medium">{locationName}</span>
            <span className="text-slate-500 dark:text-slate-400"> から </span>
            <span className="font-semibold">{label}</span>
        </p>
    );
}
