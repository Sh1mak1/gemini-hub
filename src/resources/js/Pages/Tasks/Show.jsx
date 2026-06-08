import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm } from '@inertiajs/react';

const CATEGORY_STYLES = {
    work: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-200 dark:ring-indigo-800',
    hobby: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-200 dark:ring-emerald-800',
    other: 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
};

const STATUS_STYLES = {
    pending: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-200 dark:ring-amber-800',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-200 dark:ring-emerald-800',
};

const fieldClass =
    'mt-1 block w-full rounded-md border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-400';

function Badge({ children, className }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset ${className}`}
        >
            {children}
        </span>
    );
}

function DetailItem({ label, value }) {
    return (
        <div className="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/50">
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">
                {label}
            </dt>
            <dd className="mt-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                {value || '未設定'}
            </dd>
        </div>
    );
}

export default function Show({ task, categoryOptions, statusOptions }) {
    const { data, setData, patch, errors, processing, recentlySuccessful, reset } =
        useForm({
            title: task.title ?? '',
            due_date: task.due_date ?? '',
            category: task.category,
            location: task.location ?? '',
            status: task.status,
        });

    const submit = (e) => {
        e.preventDefault();

        patch(
            route('tasks.update', {
                source: task.source,
                id: task.id,
            }),
            {
                preserveScroll: true,
            },
        );
    };

    const categoryStyle =
        CATEGORY_STYLES[task.category] ?? CATEGORY_STYLES.other;
    const statusStyle =
        STATUS_STYLES[task.status] ?? STATUS_STYLES.pending;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-indigo-600 dark:text-indigo-300">
                            Task Detail
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            タスク詳細
                        </h2>
                    </div>
                    <Link
                        href={route('tasks.index')}
                        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-500/70 dark:hover:bg-slate-800 dark:hover:text-indigo-200"
                    >
                        一覧に戻る
                    </Link>
                </div>
            }
        >
            <Head title={`タスク詳細 - ${task.title}`} />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-8 transition-colors dark:from-slate-950 dark:via-slate-950 dark:to-slate-950">
                <div className="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                    <section className="space-y-6">
                        <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                        {task.source_label} #{task.id}
                                    </p>
                                    <h3 className="mt-3 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                                        {task.title}
                                    </h3>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Badge className={categoryStyle}>
                                        {task.category_label}
                                    </Badge>
                                    <Badge className={statusStyle}>
                                        {task.status_label}
                                    </Badge>
                                    {task.is_overdue && (
                                        <Badge className="bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/60 dark:text-rose-200 dark:ring-rose-800">
                                            期限切れ
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            <dl className="mt-6 grid gap-3 sm:grid-cols-2">
                                <DetailItem label="期日" value={task.due_date} />
                                <DetailItem label="場所" value={task.location} />
                                <DetailItem label="作成日" value={task.created_at} />
                                <DetailItem
                                    label="更新日"
                                    value={task.updated_at_display}
                                />
                            </dl>
                        </div>

                        {task.raw_input && (
                            <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
                                <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    元の入力
                                </h3>
                                <p className="mt-3 whitespace-pre-wrap rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700 dark:bg-slate-950/60 dark:text-slate-200">
                                    {task.raw_input}
                                </p>
                            </div>
                        )}
                    </section>

                    <section className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
                        <header>
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                タスク情報を編集
                            </h3>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Geminiが抽出した内容や未解析タスクの情報をここで修正できます。
                            </p>
                        </header>

                        <form onSubmit={submit} className="mt-6 space-y-6">
                            <div>
                                <InputLabel htmlFor="title" value="タイトル" />
                                <TextInput
                                    id="title"
                                    className="mt-1 block w-full"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.title}
                                />
                            </div>

                            <div className="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="due_date" value="期日" />
                                    <TextInput
                                        id="due_date"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={data.due_date}
                                        onChange={(e) =>
                                            setData('due_date', e.target.value)
                                        }
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={errors.due_date}
                                    />
                                </div>

                                <div>
                                    <InputLabel htmlFor="location" value="場所" />
                                    <TextInput
                                        id="location"
                                        className="mt-1 block w-full"
                                        value={data.location}
                                        onChange={(e) =>
                                            setData('location', e.target.value)
                                        }
                                        placeholder="未設定"
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={errors.location}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="category" value="カテゴリ" />
                                    <select
                                        id="category"
                                        className={fieldClass}
                                        value={data.category}
                                        onChange={(e) =>
                                            setData('category', e.target.value)
                                        }
                                    >
                                        {categoryOptions.map((category) => (
                                            <option
                                                key={category.value}
                                                value={category.value}
                                            >
                                                {category.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        className="mt-2"
                                        message={errors.category}
                                    />
                                </div>

                                <div>
                                    <InputLabel htmlFor="status" value="状態" />
                                    <select
                                        id="status"
                                        className={fieldClass}
                                        value={data.status}
                                        onChange={(e) =>
                                            setData('status', e.target.value)
                                        }
                                    >
                                        {statusOptions.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        className="mt-2"
                                        message={errors.status}
                                    />
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                                <PrimaryButton disabled={processing}>
                                    保存する
                                </PrimaryButton>
                                <SecondaryButton
                                    type="button"
                                    disabled={processing}
                                    onClick={() => reset()}
                                >
                                    変更を戻す
                                </SecondaryButton>
                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-slate-600 dark:text-slate-400">
                                        保存しました。
                                    </p>
                                </Transition>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
