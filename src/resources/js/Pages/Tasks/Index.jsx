import TaskTimeline from '@/Components/TaskTimeline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const CATEGORY_STYLES = {
    work: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-200 dark:ring-indigo-800',
    hobby: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-200 dark:ring-emerald-800',
    other: 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
};

function StatCard({ label, value, accent }) {
    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{label}</p>
            <p className={`mt-2 text-3xl font-bold tracking-tight ${accent}`}>
                {value}
            </p>
        </div>
    );
}

function taskKey(task) {
    return `${task.source}-${task.id}`;
}

function TaskCard({ task, onComplete, completingId }) {
    const categoryStyle =
        CATEGORY_STYLES[task.category] ?? CATEGORY_STYLES.other;
    const isCompleting = completingId === taskKey(task);

    return (
        <article className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40 dark:hover:border-slate-700">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <h4
                        className={`text-sm font-semibold ${task.status === 'completed' ? 'text-slate-400 line-through dark:text-slate-600' : 'text-slate-900 dark:text-slate-100'}`}
                    >
                        <Link
                            href={route('tasks.show', {
                                source: task.source,
                                id: task.id,
                            })}
                            className="transition hover:text-indigo-600 dark:hover:text-indigo-300"
                        >
                            {task.title}
                        </Link>
                    </h4>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <span
                            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${categoryStyle}`}
                        >
                            {task.category_label}
                        </span>
                        {task.is_overdue && (
                            <span className="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-200 dark:bg-rose-950/60 dark:text-rose-200 dark:ring-rose-800">
                                期限切れ
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-2">
                    <span className="text-xs text-slate-400 dark:text-slate-500">#{task.id}</span>
                    <Link
                        href={route('tasks.show', {
                            source: task.source,
                            id: task.id,
                        })}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-indigo-500/70 dark:hover:bg-slate-800 dark:hover:text-indigo-200"
                    >
                        詳細
                    </Link>
                    {task.status === 'pending' && onComplete && (
                        <button
                            type="button"
                            onClick={() => onComplete(task.source, task.id)}
                            disabled={isCompleting}
                            className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {isCompleting ? '処理中…' : '完了'}
                        </button>
                    )}
                </div>
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div>
                    <dt className="text-slate-400 dark:text-slate-500">期日</dt>
                    <dd className="mt-1 font-medium text-slate-700 dark:text-slate-200">
                        {task.due_date ?? '未設定'}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-400 dark:text-slate-500">場所</dt>
                    <dd className="mt-1 font-medium text-slate-700 dark:text-slate-200">
                        {task.location ?? '未設定'}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-400 dark:text-slate-500">開始</dt>
                    <dd className="mt-1 font-medium text-slate-700 dark:text-slate-200">
                        {task.start_date}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-400 dark:text-slate-500">状態</dt>
                    <dd className="mt-1 font-medium text-slate-700 dark:text-slate-200">
                        {task.status_label}
                    </dd>
                </div>
            </dl>
        </article>
    );
}

function TaskList({ tasks, emptyMessage, onComplete, completingId }) {
    if (!tasks.length) {
        return (
            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-400">
                {emptyMessage}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {tasks.map((task) => (
                <TaskCard
                    key={taskKey(task)}
                    task={task}
                    onComplete={onComplete}
                    completingId={completingId}
                />
            ))}
        </div>
    );
}

export default function Index({ pendingTasks, completedTasks, timelineTasks, stats }) {
    const [activeTab, setActiveTab] = useState('pending');
    const [completingId, setCompletingId] = useState(null);

    const completeTask = (source, taskId) => {
        setCompletingId(`${source}-${taskId}`);
        router.patch(
            route('tasks.complete', { source, id: taskId }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setCompletingId(null),
            },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-indigo-600 dark:text-indigo-300">
                            AI ToDo Manager
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            タスク管理
                        </h2>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={route('debug.logs')}
                            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-500/70 dark:hover:bg-slate-800 dark:hover:text-indigo-200"
                        >
                            操作ログ
                        </Link>
                        <Link
                            href={route('debug.database')}
                            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-500/70 dark:hover:bg-slate-800 dark:hover:text-indigo-200"
                        >
                            データベース
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="タスク管理" />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-8 transition-colors dark:from-slate-950 dark:via-slate-950 dark:to-slate-950">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <section className="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            label="未完了"
                            value={stats.pending}
                            accent="text-indigo-600 dark:text-indigo-300"
                        />
                        <StatCard
                            label="完了"
                            value={stats.completed}
                            accent="text-emerald-600 dark:text-emerald-300"
                        />
                        <StatCard
                            label="期限切れ"
                            value={stats.overdue}
                            accent="text-rose-600 dark:text-rose-300"
                        />
                    </section>

                    <section>
                        <TaskTimeline tasks={timelineTasks} />
                    </section>

                    <section className="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
                        <div className="border-b border-slate-100 px-4 pt-4 dark:border-slate-800 sm:px-6">
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('pending')}
                                    className={`rounded-t-lg px-4 py-2.5 text-sm font-medium transition ${
                                        activeTab === 'pending'
                                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-200'
                                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-100'
                                    }`}
                                >
                                    未完了 ({pendingTasks.length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('completed')}
                                    className={`rounded-t-lg px-4 py-2.5 text-sm font-medium transition ${
                                        activeTab === 'completed'
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-200'
                                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-100'
                                    }`}
                                >
                                    完了 ({completedTasks.length})
                                </button>
                            </div>
                        </div>

                        <div className="p-4 sm:p-6">
                            {activeTab === 'pending' ? (
                                <TaskList
                                    tasks={pendingTasks}
                                    emptyMessage="未完了のタスクはありません"
                                    onComplete={completeTask}
                                    completingId={completingId}
                                />
                            ) : (
                                <TaskList
                                    tasks={completedTasks}
                                    emptyMessage="完了したタスクはありません"
                                />
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
