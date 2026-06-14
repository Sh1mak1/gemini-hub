export default function TourismSpotImage({ imageUrl, name }) {
    if (imageUrl) {
        return (
            <div className="relative aspect-[5/4] overflow-hidden bg-[#ebe2d4]">
                <img
                    src={imageUrl}
                    alt={name}
                    className="h-full w-full object-cover transition duration-700 hover:scale-105"
                    loading="lazy"
                />
                <div
                    aria-hidden="true"
                    className="absolute inset-0 bg-gradient-to-t from-[#2c2419]/35 via-transparent to-transparent"
                />
            </div>
        );
    }

    return (
        <div
            aria-label={`${name} の画像なし`}
            className="relative flex aspect-[5/4] items-center justify-center bg-[linear-gradient(135deg,#f3eadb,#e8dcc8)]"
        >
            <div className="text-center">
                <p className="font-serif text-sm tracking-[0.3em] text-[#9a7b4f]">風景</p>
                <p className="mt-2 text-xs text-[#8b7355]">イメージ準備中</p>
            </div>
        </div>
    );
}
