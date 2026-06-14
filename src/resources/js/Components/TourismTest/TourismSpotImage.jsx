export default function TourismSpotImage({ imageUrl, name }) {
    if (imageUrl) {
        return (
            <img
                src={imageUrl}
                alt={name}
                className="h-40 w-full rounded-xl object-cover"
                loading="lazy"
            />
        );
    }

    return (
        <div
            aria-label={`${name} の画像なし`}
            className="flex h-40 w-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-500"
        >
            画像なし
        </div>
    );
}
