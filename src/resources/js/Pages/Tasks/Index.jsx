import TaskTimeline from '@/Components/TaskTimeline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const CATEGORY_STYLES = {
    work: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    hobby: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    other: 'bg-slate-50 text-slate-700 ring-slate-200',
};

function StatCard({ label, value, accent }) {
    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className={`mt-2 text-3xl font-bold tracking-tight ${accent}`}>
                {value}
            </p>
        </div>
    );
}

function TaskCard({ task, onComplete, completingId }) {
    const categoryStyle =
        CATEGORY_STYLES[task.category] ?? CATEGORY_STYLES.other;
    const isCompleting = completingId === task.id;

    return (
        <article className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <h4
                        className={`text-sm font-semibold ${task.status === 'completed' ? 'text-slate-400 line-through' : 'text-slate-900'}`}
                    >
                        {task.title}
                    </h4>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <span
                            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${categoryStyle}`}
                        >
                            {task.category_label}
                        </span>
                        {task.is_overdue && (
                            <span className="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-200">
                                期限切れ
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-2">
                    <span className="text-xs text-slate-400">#{task.id}</span>
                    {task.status === 'pending' && onComplete && (
                        <button
                            type="button"
                            onClick={() => onComplete(task.id)}
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
                    <dt className="text-slate-400">期日</dt>
                    <dd className="mt-1 font-medium text-slate-700">
                        {task.due_date ?? '未設定'}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-400">場所</dt>
                    <dd className="mt-1 font-medium text-slate-700">
                        {task.location ?? '未設定'}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-400">開始</dt>
                    <dd className="mt-1 font-medium text-slate-700">
                        {task.start_date}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-400">状態</dt>
                    <dd className="mt-1 font-medium text-slate-700">
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
            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500">
                {emptyMessage}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {tasks.map((task) => (
                <TaskCard
                    key={task.id}
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

    const completeTask = (taskId) => {
        setCompletingId(taskId);
        router.patch(
            route('tasks.complete', taskId),
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
                    <p className="text-sm font-medium text-indigo-600">
                        AI ToDo Manager
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        タスク管理
                    </h2>
                </div>
            }
        >
            <Head title="タスク管理" />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <section className="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            label="未完了"
                            value={stats.pending}
                            accent="text-indigo-600"
                        />
                        <StatCard
                            label="完了"
                            value={stats.completed}
                            accent="text-emerald-600"
                        />
                        <StatCard
                            label="期限切れ"
                            value={stats.overdue}
                            accent="text-rose-600"
                        />
                    </section>

                    <section>
                        <TaskTimeline tasks={timelineTasks} />
                    </section>

                    <section className="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div className="border-b border-slate-100 px-4 pt-4 sm:px-6">
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('pending')}
                                    className={`rounded-t-lg px-4 py-2.5 text-sm font-medium transition ${
                                        activeTab === 'pending'
                                            ? 'bg-indigo-50 text-indigo-700'
                                            : 'text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    未完了 ({pendingTasks.length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('completed')}
                                    className={`rounded-t-lg px-4 py-2.5 text-sm font-medium transition ${
                                        activeTab === 'completed'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'text-slate-500 hover:text-slate-700'
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
