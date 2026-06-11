import AppSectionTabs from '@/Components/AppSectionTabs';
import TaskTimeline from '@/Components/TaskTimeline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const CATEGORY_STYLES = {
    work: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-200 dark:ring-indigo-800',
    hobby: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-200 dark:ring-emerald-800',
    other: 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
};

const TASK_ROW_HEIGHT = '3.25rem';
const VISIBLE_TASK_ROWS = 2;

function StatChip({ label, value, accent }) {
    return (
        <div className="flex items-center gap-1.5 rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 dark:border-slate-700 dark:bg-slate-900">
            <span className="text-[11px] text-slate-500 dark:text-slate-400">
                {label}
            </span>
            <span className={`text-sm font-bold tabular-nums ${accent}`}>
                {value}
            </span>
        </div>
    );
}

function taskKey(task) {
    return `${task.source}-${task.id}`;
}

function TaskRow({ task, onComplete, completingId }) {
    const categoryStyle =
        CATEGORY_STYLES[task.category] ?? CATEGORY_STYLES.other;
    const isCompleting = completingId === taskKey(task);

    return (
        <article
            className="flex min-w-[520px] items-center gap-2 border-b border-slate-100 px-3 last:border-b-0 dark:border-slate-800"
            style={{ height: TASK_ROW_HEIGHT }}
        >
            <div className="min-w-0 flex-1">
                <h4
                    className={`truncate text-xs font-semibold leading-tight ${task.status === 'completed' ? 'text-slate-400 line-through dark:text-slate-600' : 'text-slate-900 dark:text-slate-100'}`}
                    title={task.title}
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
                <div className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400">
                    <span
                        className={`inline-flex rounded px-1.5 py-px font-medium ring-1 ring-inset ${categoryStyle}`}
                    >
                        {task.category_label}
                    </span>
                    <span>{task.due_date ?? '期日未設定'}</span>
                    {task.location && (
                        <span className="truncate">{task.location}</span>
                    )}
                    {task.is_overdue && (
                        <span className="font-medium text-rose-600 dark:text-rose-300">
                            期限切れ
                        </span>
                    )}
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-1.5">
                <Link
                    href={route('tasks.show', {
                        source: task.source,
                        id: task.id,
                    })}
                    className="rounded border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700 dark:border-slate-700 dark:text-slate-300"
                >
                    詳細
                </Link>
                {task.status === 'pending' && onComplete && (
                    <button
                        type="button"
                        onClick={() => onComplete(task.source, task.id)}
                        disabled={isCompleting}
                        className="rounded bg-emerald-600 px-2 py-1 text-[10px] font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {isCompleting ? '…' : '完了'}
                    </button>
                )}
            </div>
        </article>
    );
}

function TaskList({ tasks, emptyMessage, onComplete, completingId }) {
    if (!tasks.length) {
        return (
            <div className="px-4 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                {emptyMessage}
            </div>
        );
    }

    return (
        <div
            className="overflow-auto"
            style={{ maxHeight: `calc(${TASK_ROW_HEIGHT} * ${VISIBLE_TASK_ROWS})` }}
        >
            <div className="min-w-full">
                {tasks.map((task) => (
                    <TaskRow
                        key={taskKey(task)}
                        task={task}
                        onComplete={onComplete}
                        completingId={completingId}
                    />
                ))}
            </div>
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
                <div>
                    <p className="text-sm font-medium text-indigo-600 dark:text-indigo-300">
                        AI ToDo Manager
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        タスク管理
                    </h2>
                </div>
            }
        >
            <Head title="タスク管理" />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-6 transition-colors dark:from-slate-950 dark:via-slate-950 dark:to-slate-950">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <AppSectionTabs activeTab="tasks" />

                    <section className="flex flex-wrap items-center gap-2">
                        <StatChip
                            label="未完了"
                            value={stats.pending}
                            accent="text-indigo-600 dark:text-indigo-300"
                        />
                        <StatChip
                            label="完了"
                            value={stats.completed}
                            accent="text-emerald-600 dark:text-emerald-300"
                        />
                        <StatChip
                            label="期限切れ"
                            value={stats.overdue}
                            accent="text-rose-600 dark:text-rose-300"
                        />
                    </section>

                    <section>
                        <TaskTimeline tasks={timelineTasks} />
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
                        <div className="flex items-center gap-2 border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setActiveTab('pending')}
                                className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${
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
                                className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${
                                    activeTab === 'completed'
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-200'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-100'
                                }`}
                            >
                                完了 ({completedTasks.length})
                            </button>
                            <span className="ml-auto text-[10px] text-slate-400 dark:text-slate-500">
                                スクロールで全件
                            </span>
                        </div>

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
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
