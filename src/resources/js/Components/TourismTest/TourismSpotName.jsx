export default function TourismSpotName({ name, index }) {
    return (
        <div className="flex items-start gap-3">
            <span className="mt-1 font-serif text-sm tracking-[0.2em] text-[#9a7b4f]">
                {String(index).padStart(2, '0')}
            </span>
            <h3 className="font-serif text-xl font-semibold leading-snug tracking-wide text-[#2c2419] sm:text-2xl">
                {name}
            </h3>
        </div>
    );
}
