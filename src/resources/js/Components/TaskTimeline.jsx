import { useMemo } from 'react';

const CATEGORY_COLORS = {
    work: {
        bar: 'from-indigo-500 to-violet-500',
        dot: 'bg-indigo-500',
    },
    hobby: {
        bar: 'from-emerald-500 to-teal-500',
        dot: 'bg-emerald-500',
    },
    other: {
        bar: 'from-slate-500 to-slate-600',
        dot: 'bg-slate-500',
    },
};

const LABEL_COLUMN_WIDTH = '220px';

function parseDate(value) {
    return new Date(`${value}T00:00:00`);
}

function addDays(date, days) {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
}

function formatShortDate(value) {
    return parseDate(value).toLocaleDateString('ja-JP', {
        month: 'numeric',
        day: 'numeric',
    });
}

function daysBetween(start, end) {
    return Math.max(
        1,
        Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)),
    );
}

export default function TaskTimeline({ tasks }) {
    const { rangeStart, totalDays, ticks, rows } = useMemo(() => {
        if (!tasks.length) {
            return {
                rangeStart: null,
                totalDays: 1,
                ticks: [],
                rows: [],
            };
        }

        const starts = tasks.map((task) => parseDate(task.start_date));
        const ends = tasks.map((task) => parseDate(task.timeline_end));
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let start = new Date(Math.min(...starts.map((d) => d.getTime())));
        let end = new Date(
            Math.max(...ends.map((d) => d.getTime()), today.getTime()),
        );

        start = addDays(start, -1);
        end = addDays(end, 2);

        const dayCount = daysBetween(start, end);
        const tickCount = Math.min(dayCount, 8);
        const tickStep = Math.max(1, Math.floor(dayCount / tickCount));

        const tickMarks = [];
        for (let i = 0; i <= dayCount; i += tickStep) {
            tickMarks.push(addDays(start, i));
        }

        const computedRows = tasks.map((task) => {
            const taskStart = parseDate(task.start_date);
            const taskEnd = parseDate(task.timeline_end);
            const offsetDays = daysBetween(start, taskStart) - 1;
            const durationDays = daysBetween(taskStart, taskEnd);

            return {
                ...task,
                offsetPercent: (offsetDays / dayCount) * 100,
                widthPercent: Math.max((durationDays / dayCount) * 100, 4),
            };
        });

        return {
            rangeStart: start,
            totalDays: dayCount,
            ticks: tickMarks,
            rows: computedRows,
        };
    }, [tasks]);

    if (!tasks.length) {
        return (
            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-12 text-center text-sm text-slate-500">
                タイムラインに表示するタスクがありません
            </div>
        );
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayOffset =
        rangeStart !== null
            ? ((daysBetween(rangeStart, today) - 1) / totalDays) * 100
            : 0;
    const showTodayMarker = todayOffset >= 0 && todayOffset <= 100;

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-6 py-4">
                <h3 className="text-base font-semibold text-slate-900">
                    タイムライン
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                    開始日から期日までの流れを可視化しています
                </p>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[760px]">
                    {/* ヘッダー */}
                    <div className="flex border-b border-slate-100 bg-slate-50/50">
                        <div
                            className="shrink-0 border-r border-slate-200 px-4 py-3 text-xs font-medium text-slate-500"
                            style={{ width: LABEL_COLUMN_WIDTH }}
                        >
                            タスク
                        </div>
                        <div className="relative min-w-0 flex-1 px-4 py-3">
                            <div className="flex justify-between text-xs text-slate-400">
                                {ticks.map((tick) => (
                                    <span key={tick.toISOString()}>
                                        {formatShortDate(
                                            tick.toISOString().slice(0, 10),
                                        )}
                                    </span>
                                ))}
                            </div>
                            {showTodayMarker && (
                                <div
                                    className="pointer-events-none absolute top-1 z-20 -translate-x-1/2"
                                    style={{ left: `${todayOffset}%` }}
                                >
                                    <span className="rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-semibold text-white shadow-sm">
                                        今日
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* 本体: ラベル列 + チャート列（今日ラインは1本のみ） */}
                    <div className="flex">
                        <div
                            className="shrink-0 border-r border-slate-200 bg-white"
                            style={{ width: LABEL_COLUMN_WIDTH }}
                        >
                            {rows.map((task) => {
                                const colors =
                                    CATEGORY_COLORS[task.category] ??
                                    CATEGORY_COLORS.other;
                                const isCompleted =
                                    task.status === 'completed';

                                return (
                                    <div
                                        key={`${task.source}-${task.id}`}
                                        className="border-b border-slate-100 px-4 py-4 last:border-b-0"
                                    >
                                        <div className="flex items-start gap-2">
                                            <span
                                                className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${colors.dot}`}
                                            />
                                            <div className="min-w-0">
                                                <p
                                                    className={`text-sm font-semibold leading-snug ${isCompleted ? 'text-slate-400 line-through' : 'text-slate-900'}`}
                                                >
                                                    {task.title}
                                                </p>
                                                <p className="mt-1.5 text-xs text-slate-500">
                                                    開始{' '}
                                                    <span className="font-medium text-slate-700">
                                                        {task.start_date}
                                                    </span>
                                                </p>
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    期日{' '}
                                                    <span className="font-medium text-slate-700">
                                                        {task.due_date ??
                                                            '未設定'}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="relative min-w-0 flex-1 bg-slate-50/60 px-4">
                            {showTodayMarker && (
                                <div className="pointer-events-none absolute inset-y-0 left-4 right-4 z-10">
                                    <div
                                        className="absolute top-0 bottom-0 w-1 -translate-x-1/2 rounded-full bg-rose-500 shadow-[0_0_0_2px_rgba(244,63,94,0.15)]"
                                        style={{ left: `${todayOffset}%` }}
                                    />
                                </div>
                            )}

                            {rows.map((task) => {
                                const colors =
                                    CATEGORY_COLORS[task.category] ??
                                    CATEGORY_COLORS.other;
                                const isCompleted =
                                    task.status === 'completed';

                                return (
                                    <div
                                        key={`${task.source}-${task.id}`}
                                        className="border-b border-slate-100 py-4 last:border-b-0"
                                    >
                                        <div className="relative h-8 rounded-lg bg-white/80 ring-1 ring-slate-200/80">
                                            <div
                                                className={`absolute top-1/2 h-3 -translate-y-1/2 rounded-full bg-gradient-to-r shadow-sm ${colors.bar} ${isCompleted ? 'opacity-35' : 'opacity-95'}`}
                                                style={{
                                                    left: `${task.offsetPercent}%`,
                                                    width: `${task.widthPercent}%`,
                                                }}
                                            >
                                                <span className="absolute -right-0.5 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rotate-45 rounded-sm bg-white shadow" />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
