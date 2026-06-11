import { Link } from '@inertiajs/react';

const TABS = [
    {
        key: 'tasks',
        label: 'ToDoリスト',
        href: () => route('tasks.index'),
    },
    {
        key: 'logs',
        label: '操作ログ',
        href: () => route('debug.logs'),
    },
    {
        key: 'database',
        label: 'DB',
        href: () => route('debug.database'),
    },
];

export default function AppSectionTabs({ activeTab }) {
    return (
        <nav
            aria-label="表示切り替え"
            className="rounded-2xl border border-slate-200/80 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40"
        >
            <div className="grid grid-cols-3 gap-1" role="tablist">
                {TABS.map((tab) => {
                    const isActive = tab.key === activeTab;

                    return (
                        <Link
                            key={tab.key}
                            href={tab.href()}
                            role="tab"
                            aria-selected={isActive}
                            className={`rounded-xl px-4 py-2.5 text-center text-sm font-semibold transition ${
                                isActive
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100'
                            }`}
                        >
                            {tab.label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
