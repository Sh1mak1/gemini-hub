import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

const LEVEL_STYLES = {
    error: 'border-rose-200 bg-rose-50 text-rose-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    info: 'border-slate-200 bg-white text-slate-800',
    debug: 'border-slate-200 bg-slate-50 text-slate-700',
};

function LogEntry({ entry }) {
    const levelStyle = LEVEL_STYLES[entry.level] ?? LEVEL_STYLES.info;
    const context = { ...entry.context };
    delete context.operation;
    delete context.step;

    const hasContext = Object.keys(context).length > 0;

    return (
        <article className={`rounded-lg border px-4 py-3 ${levelStyle}`}>
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <time className="font-mono text-slate-500">{entry.timestamp}</time>
                <span className="rounded-full bg-black/5 px-2 py-0.5 font-semibold uppercase tracking-wide">
                    {entry.level}
                </span>
                {entry.operation && (
                    <span className="rounded-full bg-indigo-100 px-2 py-0.5 font-medium text-indigo-700">
                        {entry.operation}
                    </span>
                )}
                {entry.step && (
                    <span className="font-medium">{entry.step}</span>
                )}
            </div>
            {hasContext && (
                <pre className="mt-3 overflow-x-auto rounded-md bg-black/5 p-3 font-mono text-xs leading-relaxed">
                    {JSON.stringify(context, null, 2)}
                </pre>
            )}
        </article>
    );
}

export default function Logs({
    entries: initialEntries,
    availableDates,
    operations: initialOperations,
    filters,
}) {
    const [entries, setEntries] = useState(initialEntries);
    const [operations, setOperations] = useState(initialOperations);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [loading, setLoading] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(new Date());

    const applyFilters = (nextFilters) => {
        router.get(route('debug.logs'), nextFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const fetchEntries = useCallback(async () => {
        const params = new URLSearchParams({
            date: filters.date,
            operation: filters.operation,
            level: filters.level,
        });

        setLoading(true);

        try {
            const response = await fetch(`${route('debug.logs.entries')}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            setEntries(data.entries);
            setOperations(data.operations);
            setLastUpdated(new Date());
        } finally {
            setLoading(false);
        }
    }, [filters.date, filters.operation, filters.level]);

    useEffect(() => {
        setEntries(initialEntries);
        setOperations(initialOperations);
    }, [initialEntries, initialOperations]);

    useEffect(() => {
        if (!autoRefresh) {
            return undefined;
        }

        const interval = window.setInterval(fetchEntries, 3000);

        return () => window.clearInterval(interval);
    }, [autoRefresh, fetchEntries]);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-sm font-medium text-indigo-600">Debug</p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        操作ログ
                    </h2>
                </div>
            }
        >
            <Head title="操作ログ" />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-6">
                        <div className="flex flex-wrap items-end gap-4">
                            <label className="flex flex-col gap-1 text-sm">
                                <span className="font-medium text-slate-600">日付</span>
                                <select
                                    value={filters.date}
                                    onChange={(event) =>
                                        applyFilters({
                                            ...filters,
                                            date: event.target.value,
                                        })
                                    }
                                    className="rounded-lg border-slate-300 text-sm shadow-sm"
                                >
                                    {availableDates.map((date) => (
                                        <option key={date} value={date}>
                                            {date}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="flex flex-col gap-1 text-sm">
                                <span className="font-medium text-slate-600">操作</span>
                                <select
                                    value={filters.operation}
                                    onChange={(event) =>
                                        applyFilters({
                                            ...filters,
                                            operation: event.target.value,
                                        })
                                    }
                                    className="rounded-lg border-slate-300 text-sm shadow-sm"
                                >
                                    <option value="">すべて</option>
                                    {operations.map((operation) => (
                                        <option key={operation} value={operation}>
                                            {operation}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="flex flex-col gap-1 text-sm">
                                <span className="font-medium text-slate-600">レベル</span>
                                <select
                                    value={filters.level}
                                    onChange={(event) =>
                                        applyFilters({
                                            ...filters,
                                            level: event.target.value,
                                        })
                                    }
                                    className="rounded-lg border-slate-300 text-sm shadow-sm"
                                >
                                    <option value="">すべて</option>
                                    <option value="info">info</option>
                                    <option value="warning">warning</option>
                                    <option value="error">error</option>
                                </select>
                            </label>

                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    checked={autoRefresh}
                                    onChange={(event) =>
                                        setAutoRefresh(event.target.checked)
                                    }
                                    className="rounded border-slate-300 text-indigo-600"
                                />
                                3秒ごとに自動更新
                            </label>

                            <button
                                type="button"
                                onClick={fetchEntries}
                                disabled={loading}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                {loading ? '更新中…' : '今すぐ更新'}
                            </button>
                        </div>

                        <p className="mt-4 text-xs text-slate-500">
                            最新 {entries.length} 件
                            {autoRefresh && '（自動更新ON）'}
                            {' · '}
                            最終更新: {lastUpdated.toLocaleTimeString('ja-JP')}
                        </p>
                    </section>

                    <section className="space-y-3">
                        {entries.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-12 text-center text-sm text-slate-500">
                                ログがありません。Slack や Drafts
                                で操作するとここに記録されます。
                            </div>
                        ) : (
                            [...entries].reverse().map((entry, index) => (
                                <LogEntry
                                    key={`${entry.timestamp}-${entry.operation}-${entry.step}-${index}`}
                                    entry={entry}
                                />
                            ))
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
