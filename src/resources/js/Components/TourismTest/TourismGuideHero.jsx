export default function TourismGuideHero({
    locationName,
    processing,
    error,
    fieldError,
    onChange,
    onSubmit,
}) {
    return (
        <section className="relative overflow-hidden rounded-3xl border border-[#d9cdb8] bg-[#fffdf8] shadow-[0_24px_60px_-32px_rgba(62,44,28,0.45)]">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 opacity-[0.35]"
                style={{
                    backgroundImage:
                        'radial-gradient(circle at 20% 20%, rgba(196,163,90,0.18), transparent 42%), radial-gradient(circle at 80% 0%, rgba(107,76,59,0.12), transparent 36%), linear-gradient(135deg, rgba(255,252,245,0.9), rgba(247,239,226,0.95))',
                }}
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -right-8 top-8 hidden h-40 w-40 rounded-full border border-[#c4a35a]/30 md:block"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute bottom-6 left-6 hidden text-[10rem] font-serif leading-none text-[#6b4c3b]/[0.04] md:block"
            >
                旅
            </div>

            <div className="relative px-6 py-10 sm:px-10 sm:py-14">
                <p className="text-xs font-medium tracking-[0.35em] text-[#8b7355]">
                    LOCAL GUIDE
                </p>
                <h1 className="mt-3 font-serif text-3xl font-semibold tracking-wide text-[#2c2419] sm:text-4xl">
                    周辺の名所をご案内
                </h1>
                <p className="mt-4 max-w-2xl text-sm leading-7 text-[#5c4f42] sm:text-base">
                    お宿の名称や近隣の目印を入力すると、周辺の見どころを三つ厳選してご紹介します。
                </p>

                <form
                    onSubmit={onSubmit}
                    className="mt-8 max-w-3xl rounded-2xl border border-[#e7dcc8] bg-white/80 p-5 backdrop-blur-sm sm:p-6"
                >
                    <label
                        htmlFor="location_name"
                        className="block text-xs font-medium tracking-[0.2em] text-[#8b7355]"
                    >
                        お宿・起点となる場所
                    </label>
                    <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <input
                            id="location_name"
                            type="text"
                            value={locationName}
                            onChange={onChange}
                            placeholder="例: 箱根 強羅温泉、金沢 茶屋町"
                            className="w-full border-0 border-b-2 border-[#d9cdb8] bg-transparent px-0 py-3 font-serif text-lg text-[#2c2419] placeholder:text-[#b6a48d] focus:border-[#9a7b4f] focus:outline-none focus:ring-0"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="shrink-0 rounded-full border border-[#9a7b4f] bg-[#6b4c3b] px-6 py-3 text-sm font-medium tracking-[0.12em] text-[#fffaf2] transition hover:bg-[#5a3f31] disabled:opacity-50"
                        >
                            {processing ? '案内を作成中…' : '案内を見る'}
                        </button>
                    </div>
                    {fieldError && (
                        <p className="mt-3 text-sm text-[#9b4d3a]">{fieldError}</p>
                    )}
                    {error && (
                        <p className="mt-3 rounded-xl border border-[#e8c9bf] bg-[#fff6f3] px-4 py-3 text-sm text-[#8f4d3d]">
                            {error}
                        </p>
                    )}
                </form>
            </div>
        </section>
    );
}
