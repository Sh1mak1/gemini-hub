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

const LABEL_COLUMN_WIDTH = '160px';
const ROW_HEIGHT = '2.75rem';
const VISIBLE_ROWS = 3;

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

function distanceFromToday(task, today) {
    const anchor = task.due_date
        ? parseDate(task.due_date)
        : parseDate(task.timeline_end);

    return Math.abs(anchor.getTime() - today.getTime());
}

function sortByNearestToToday(tasks) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return [...tasks].sort((a, b) => {
        if (a.status !== b.status) {
            return a.status === 'pending' ? -1 : 1;
        }

        return distanceFromToday(a, today) - distanceFromToday(b, today);
    });
}

export default function TaskTimeline({ tasks }) {
    const { rangeStart, totalDays, ticks, rows } = useMemo(() => {
        const sortedTasks = sortByNearestToToday(tasks);

        if (!sortedTasks.length) {
            return {
                rangeStart: null,
                totalDays: 1,
                ticks: [],
                rows: [],
            };
        }

        const ends = sortedTasks.map((task) => parseDate(task.timeline_end));
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let start = today;
        let end = new Date(
            Math.max(...ends.map((d) => d.getTime()), today.getTime()),
        );

        end = addDays(end, 2);

        const dayCount = daysBetween(start, end);
        const tickCount = Math.min(dayCount, 8);
        const tickStep = Math.max(1, Math.floor(dayCount / tickCount));

        const tickMarks = [];
        for (let i = 0; i <= dayCount; i += tickStep) {
            tickMarks.push(addDays(start, i));
        }

        const computedRows = sortedTasks.map((task) => {
            const taskStart = parseDate(task.start_date);
            const taskEnd = parseDate(task.timeline_end);
            const effectiveStart = taskStart < today ? today : taskStart;
            const offsetDays = daysBetween(start, effectiveStart) - 1;
            const durationDays = daysBetween(effectiveStart, taskEnd);

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
            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-6 text-center text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-400">
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
        <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
            <div className="flex items-center justify-between border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    タイムライン
                </h3>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">
                    期日が近い順 · スクロールで全件
                </p>
            </div>

            <div
                className="overflow-auto"
                style={{ maxHeight: `calc(${ROW_HEIGHT} * ${VISIBLE_ROWS} + 2.5rem)` }}
            >
                <div className="min-w-[640px]">
                    <div className="sticky top-0 z-30 flex border-b border-slate-100 bg-slate-50/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
                        <div
                            className="sticky left-0 z-40 shrink-0 border-r border-slate-200 bg-slate-50/95 px-3 py-2 text-[10px] font-medium text-slate-500 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95 dark:text-slate-400"
                            style={{ width: LABEL_COLUMN_WIDTH }}
                        >
                            タスク
                        </div>
                        <div className="relative min-w-0 flex-1 px-3 py-2">
                            <div className="flex justify-between text-[10px] text-slate-400 dark:text-slate-500">
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
                                    className="pointer-events-none absolute top-1 z-20 flex w-10 -translate-x-1/2 justify-center"
                                    style={{ left: `${todayOffset}%` }}
                                >
                                    <span className="timeline-today-label rounded-full bg-rose-500 px-2 py-0.5 text-center text-[10px] font-bold leading-none text-white shadow-sm ring-2 ring-white/80 dark:ring-slate-950/80">
                                        今日
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex">
                        <div
                            className="sticky left-0 z-20 shrink-0 border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
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
                                        key={`${task.source}-${task.id}-label`}
                                        className="flex items-center border-b border-slate-100 px-3 last:border-b-0 dark:border-slate-800"
                                        style={{ height: ROW_HEIGHT }}
                                    >
                                        <div className="flex min-w-0 items-center gap-1.5">
                                            <span
                                                className={`h-1.5 w-1.5 shrink-0 rounded-full ${colors.dot}`}
                                            />
                                            <div className="min-w-0">
                                                <p
                                                    className={`truncate text-xs font-medium leading-tight ${isCompleted ? 'text-slate-400 line-through dark:text-slate-600' : 'text-slate-900 dark:text-slate-100'}`}
                                                    title={task.title}
                                                >
                                                    {task.title}
                                                </p>
                                                <p className="truncate text-[10px] text-slate-400 dark:text-slate-500">
                                                    {task.due_date ?? '期日未設定'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="relative min-w-0 flex-1 bg-slate-50/60 dark:bg-slate-950/70">
                            {showTodayMarker && (
                                <div className="pointer-events-none absolute inset-y-0 left-3 right-3 z-10">
                                    <div
                                        className="timeline-today-marker absolute top-0 bottom-0 w-0.5 -translate-x-1/2 rounded-full bg-rose-500"
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
                                        key={`${task.source}-${task.id}-bar`}
                                        className="flex items-center border-b border-slate-100 px-3 last:border-b-0 dark:border-slate-800"
                                        style={{ height: ROW_HEIGHT }}
                                    >
                                        <div className="timeline-bar-track relative h-5 w-full rounded bg-white/80 ring-1 ring-slate-200/80 dark:bg-slate-900/80 dark:ring-slate-800">
                                            <div
                                                className={`timeline-bar-fill absolute top-1/2 h-2 -translate-y-1/2 rounded-full bg-gradient-to-r ${colors.bar} ${isCompleted ? 'timeline-bar-completed opacity-35' : 'opacity-95'}`}
                                                style={{
                                                    left: `${task.offsetPercent}%`,
                                                    width: `${task.widthPercent}%`,
                                                }}
                                            />
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
