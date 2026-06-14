import AppSectionTabs from '@/Components/AppSectionTabs';
import TourismSpotCard from '@/Components/TourismTest/TourismSpotCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

function SearchHistoryItem({ search }) {
    const spotNames = search.spots.map((spot) => spot.name).join(' / ');

    return (
        <li className="rounded-lg border border-slate-200 px-4 py-3 text-sm dark:border-slate-800">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-semibold text-slate-900 dark:text-slate-100">
                    {search.location_name}
                </span>
                <span
                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                        search.status === 'completed'
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200'
                            : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200'
                    }`}
                >
                    {search.status}
                </span>
            </div>
            {search.status === 'completed' ? (
                <p className="mt-1 text-slate-500 dark:text-slate-400">{spotNames}</p>
            ) : (
                <p className="mt-1 text-rose-600 dark:text-rose-300">
                    {search.error_message || '検索に失敗しました'}
                </p>
            )}
        </li>
    );
}

export default function Tourism({ recentSearches, latestResult, error }) {
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
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-sm font-medium text-indigo-600 dark:text-indigo-300">
                        Debug / Test
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        観光情報テスト
                    </h2>
                </div>
            }
        >
            <Head title="観光情報テスト" />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-8 transition-colors dark:from-slate-950 dark:via-slate-950 dark:to-slate-950">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AppSectionTabs activeTab="tourism" />

                    <section className="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100 sm:p-6">
                        テスト実装です。Gemini の推定に基づく観光情報を表示します。不要になったら
                        <code className="mx-1 rounded bg-amber-100 px-1 py-0.5 text-xs dark:bg-amber-900/50">
                            work-logs/2026-06-14-tourism-test-spec.md
                        </code>
                        の削除手順に従って除去してください。
                    </section>

                    <section className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40 sm:p-6">
                        <form onSubmit={submit} className="flex flex-col gap-4 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <label
                                    htmlFor="location_name"
                                    className="block text-sm font-medium text-slate-700 dark:text-slate-200"
                                >
                                    土地名・地点名
                                </label>
                                <input
                                    id="location_name"
                                    type="text"
                                    value={form.data.location_name}
                                    onChange={(event) =>
                                        form.setData('location_name', event.target.value)
                                    }
                                    placeholder="例: 金沢駅、富士山五合目"
                                    className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                />
                                {form.errors.location_name && (
                                    <p className="mt-2 text-sm text-rose-600 dark:text-rose-300">
                                        {form.errors.location_name}
                                    </p>
                                )}
                            </div>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50"
                            >
                                {form.processing ? '検索中…' : '近辺の観光地を検索'}
                            </button>
                        </form>

                        {error && (
                            <p className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-100">
                                {error}
                            </p>
                        )}
                    </section>

                    {latestResult?.status === 'completed' && latestResult.spots.length > 0 && (
                        <section className="space-y-4">
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                検索結果: {latestResult.location_name}
                            </h3>
                            <div className="grid gap-4 md:grid-cols-3">
                                {latestResult.spots.map((spot) => (
                                    <TourismSpotCard
                                        key={spot.name}
                                        spot={spot}
                                        locationName={latestResult.location_name}
                                    />
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40 sm:p-6">
                        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                            検索履歴（DB）
                        </h3>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            直近 10 件。操作ログは「操作ログ」タブで
                            <code className="mx-1 rounded bg-slate-100 px-1 text-xs dark:bg-slate-800">
                                tourism.test
                            </code>
                            を確認できます。
                        </p>
                        <ul className="mt-4 space-y-2">
                            {recentSearches.length === 0 ? (
                                <li className="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                    まだ検索履歴がありません
                                </li>
                            ) : (
                                recentSearches.map((search) => (
                                    <SearchHistoryItem key={search.id} search={search} />
                                ))
                            )}
                        </ul>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
