import AppSectionTabs from '@/Components/AppSectionTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function Database({ tables, selectedTable, tableData }) {
    const changeTable = (table) => {
        router.get(
            route('debug.database'),
            { table, page: 1 },
            { preserveState: true, preserveScroll: true },
        );
    };

    const changePage = (page) => {
        router.get(
            route('debug.database'),
            { table: selectedTable, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-sm font-medium text-indigo-600 dark:text-indigo-300">Debug</p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        データベース
                    </h2>
                </div>
            }
        >
            <Head title="データベース" />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-8 transition-colors dark:from-slate-950 dark:via-slate-950 dark:to-slate-950">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AppSectionTabs activeTab="database" />

                    <section className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40 sm:p-6">
                        <div className="flex flex-wrap gap-2">
                            {tables.map((table) => (
                                <button
                                    key={table}
                                    type="button"
                                    onClick={() => changeTable(table)}
                                    className={`rounded-lg px-3 py-1.5 text-sm font-medium transition ${
                                        table === selectedTable
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                                    }`}
                                >
                                    {table}
                                </button>
                            ))}
                        </div>

                        <p className="mt-4 text-sm text-slate-500 dark:text-slate-400">
                            {selectedTable}: {tableData.total} 件（閲覧のみ）
                        </p>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead className="bg-slate-50 dark:bg-slate-950/70">
                                    <tr>
                                        {tableData.columns.map((column) => (
                                            <th
                                                key={column}
                                                className="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300"
                                            >
                                                {column}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {tableData.rows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={tableData.columns.length}
                                                className="px-4 py-10 text-center text-slate-500 dark:text-slate-400"
                                            >
                                                データがありません
                                            </td>
                                        </tr>
                                    ) : (
                                        tableData.rows.map((row, rowIndex) => (
                                            <tr
                                                key={rowIndex}
                                                className="hover:bg-slate-50/80 dark:hover:bg-slate-800/60"
                                            >
                                                {tableData.columns.map((column) => (
                                                    <td
                                                        key={column}
                                                        className="max-w-xs whitespace-pre-wrap break-all px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-200"
                                                    >
                                                        {row[column] === null ||
                                                        row[column] === undefined
                                                            ? '—'
                                                            : String(row[column])}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {tableData.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm dark:border-slate-800">
                                <span className="text-slate-500 dark:text-slate-400">
                                    {tableData.page} / {tableData.last_page} ページ
                                </span>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        disabled={tableData.page <= 1}
                                        onClick={() =>
                                            changePage(tableData.page - 1)
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                    >
                                        前へ
                                    </button>
                                    <button
                                        type="button"
                                        disabled={
                                            tableData.page >= tableData.last_page
                                        }
                                        onClick={() =>
                                            changePage(tableData.page + 1)
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                    >
                                        次へ
                                    </button>
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
