import AppSectionTabs from '@/Components/AppSectionTabs';
import TourismGuideHero from '@/Components/TourismTest/TourismGuideHero';
import TourismMap from '@/Components/TourismTest/TourismMap';
import TourismSpotCard from '@/Components/TourismTest/TourismSpotCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

function SearchHistoryItem({ search }) {
    const spotNames = search.spots.map((spot) => spot.name).join(' / ');

    return (
        <li className="rounded-xl border border-[#e7dcc8] bg-white/70 px-4 py-3 text-sm text-[#5c4f42]">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium text-[#2c2419]">{search.location_name}</span>
                <span
                    className={`rounded-full px-2 py-0.5 text-xs ${
                        search.status === 'completed'
                            ? 'bg-[#e8f0e8] text-[#4f6b4f]'
                            : 'bg-[#f8e8e4] text-[#8f4d3d]'
                    }`}
                >
                    {search.status === 'completed' ? '案内済み' : '失敗'}
                </span>
            </div>
            {search.status === 'completed' ? (
                <p className="mt-1 text-[#8b7355]">{spotNames}</p>
            ) : (
                <p className="mt-1 text-[#9b4d3a]">
                    {search.error_message || '検索に失敗しました'}
                </p>
            )}
        </li>
    );
}

export default function Tourism({ recentSearches, latestResult, error }) {
    const [activeSpotIndex, setActiveSpotIndex] = useState(null);
    const form = useForm({
        location_name: latestResult?.location_name ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('debug.tourism.search'), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="周辺案内">
                <link
                    href="https://fonts.bunny.net/css?family=noto-serif-jp:400,600,700|noto-sans-jp:400,500"
                    rel="stylesheet"
                />
            </Head>

            <div className="tourism-guide min-h-screen bg-[#f3eadb] font-['Noto_Sans_JP',sans-serif] text-[#2c2419]">
                <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <details className="group mb-6 rounded-2xl border border-[#d9cdb8]/80 bg-[#fffdf8]/70">
                        <summary className="cursor-pointer list-none px-4 py-3 text-xs tracking-[0.2em] text-[#8b7355] marker:content-none [&::-webkit-details-marker]:hidden">
                            <span className="inline-flex items-center gap-2">
                                <span className="rounded-full border border-[#d9cdb8] px-2 py-0.5">
                                    開発メニュー
                                </span>
                                <span className="opacity-70">ToDo / ログ / DB / 周辺案内</span>
                            </span>
                        </summary>
                        <div className="border-t border-[#e7dcc8] p-3">
                            <AppSectionTabs activeTab="tourism" />
                        </div>
                    </details>

                    <TourismGuideHero
                        locationName={form.data.location_name}
                        processing={form.processing}
                        error={error}
                        fieldError={form.errors.location_name}
                        onChange={(event) =>
                            form.setData('location_name', event.target.value)
                        }
                        onSubmit={submit}
                    />

                    {latestResult?.status === 'completed' && latestResult.spots.length > 0 && (
                        <section className="mt-12">
                            <div className="text-center">
                                <p className="text-xs tracking-[0.35em] text-[#9a7b4f]">
                                    RECOMMENDED
                                </p>
                                <h2 className="mt-2 font-['Noto_Serif_JP',serif] text-2xl font-semibold tracking-[0.18em] text-[#2c2419] sm:text-3xl">
                                    おすすめの三箇所
                                </h2>
                                <p className="mt-3 text-sm text-[#6b4c3b]">
                                    <span className="font-medium">{latestResult.location_name}</span>
                                    を起点に、近隣の見どころをご案内します
                                </p>
                            </div>

                            <div className="mt-8">
                                <TourismMap
                                    center={{
                                        latitude: latestResult.latitude,
                                        longitude: latestResult.longitude,
                                    }}
                                    locationName={latestResult.location_name}
                                    spots={latestResult.spots}
                                    activeSpotIndex={activeSpotIndex}
                                    onSpotSelect={setActiveSpotIndex}
                                />
                            </div>

                            <div className="mt-8 grid gap-6 lg:grid-cols-3">
                                {latestResult.spots.map((spot, index) => (
                                    <TourismSpotCard
                                        key={spot.name}
                                        spot={spot}
                                        locationName={latestResult.location_name}
                                        index={index + 1}
                                        highlighted={activeSpotIndex === index}
                                    />
                                ))}
                            </div>
                        </section>
                    )}

                    {!latestResult && recentSearches.length === 0 && (
                        <section className="mt-12 rounded-3xl border border-dashed border-[#d9cdb8] bg-[#fffdf8]/60 px-6 py-14 text-center">
                            <p className="font-['Noto_Serif_JP',serif] text-lg tracking-[0.2em] text-[#6b4c3b]">
                                お宿の名称を入力して、周辺案内をはじめてください
                            </p>
                        </section>
                    )}

                    <details className="group mt-12 rounded-2xl border border-[#d9cdb8]/70 bg-[#fffdf8]/50">
                        <summary className="cursor-pointer list-none px-5 py-4 text-sm text-[#8b7355] marker:content-none [&::-webkit-details-marker]:hidden">
                            管理用：検索履歴（直近 10 件）
                        </summary>
                        <div className="border-t border-[#e7dcc8] px-5 py-4">
                            <p className="text-xs text-[#8b7355]">
                                DB と操作ログ（
                                <code className="rounded bg-[#f3eadb] px-1">tourism.test</code>
                                ）にも記録されます。
                            </p>
                            <ul className="mt-4 space-y-2">
                                {recentSearches.length === 0 ? (
                                    <li className="rounded-xl border border-dashed border-[#d9cdb8] px-4 py-8 text-center text-sm text-[#8b7355]">
                                        まだ検索履歴がありません
                                    </li>
                                ) : (
                                    recentSearches.map((search) => (
                                        <SearchHistoryItem key={search.id} search={search} />
                                    ))
                                )}
                            </ul>
                        </div>
                    </details>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
