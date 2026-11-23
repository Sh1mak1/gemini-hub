import AppSectionTabs from '@/Components/AppSectionTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const STATUS_LABEL = {
    ongoing: '信号は続いている',
    cleared: '向こうで目処が立った',
    lost: '信号が途切れた',
};

export default function Contact({ game: initialGame }) {
    const [game, setGame] = useState(initialGame);
    const [sentence, setSentence] = useState('');
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(null);
    const [error, setError] = useState(null);
    const [debugEnabled, setDebugEnabled] = useState(false);
    const [debugState, setDebugState] = useState(null);
    const [resourceDraft, setResourceDraft] = useState(initialGame?.resource_remaining ?? 0);
    const debugEnabledRef = useRef(false);

    useEffect(() => {
        try {
            const enabled = window.localStorage.getItem('contact-debug-mode') === '1';
            debugEnabledRef.current = enabled;
            setDebugEnabled(enabled);
        } catch {
            debugEnabledRef.current = false;
        }
    }, []);

    useEffect(() => {
        setResourceDraft(game?.resource_remaining ?? 0);
    }, [game?.id, game?.resource_remaining]);

    useEffect(() => {
        if (!debugEnabled || !game?.id) {
            return undefined;
        }

        let cancelled = false;

        fetch(route('debug.contact.debug', game.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((payload) => {
                if (!cancelled) {
                    setDebugState(payload.debug ?? null);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('デバッグ情報を読めませんでした。');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [debugEnabled, game?.id]);

    const toggleDebug = () => {
        setDebugEnabled((current) => {
            const next = !current;
            debugEnabledRef.current = next;

            try {
                window.localStorage.setItem('contact-debug-mode', next ? '1' : '0');
            } catch {
                // The toggle still works for this visit.
            }

            if (!next) {
                setDebugState(null);
            }

            return next;
        });
    };

    const postJson = async (url, body, method = 'POST') => {
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (debugEnabledRef.current) {
            headers['X-Contact-Debug'] = '1';
        }

        const response = await fetch(url, {
            method,
            headers,
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (response.status === 422) {
            setError(
                payload.error
                    ?? payload.errors?.sentence?.[0]
                    ?? payload.errors?.message?.[0]
                    ?? '入力内容を確認してください。',
            );

            if (payload.game) {
                setGame(payload.game);
            }

            return false;
        }

        if (!response.ok) {
            setError('処理に失敗しました。');

            return false;
        }

        setGame(payload.game ?? null);
        setError(payload.error);

        if (payload.debug) {
            setDebugState(payload.debug);
        }

        return payload.error == null;
    };

    const start = async (event) => {
        event.preventDefault();
        setProcessing('start');
        setError(null);

        try {
            const started = await postJson(route('debug.contact.start'), { sentence });

            if (started) {
                setSentence('');
                setMessage('');
            }
        } catch {
            setError('処理に失敗しました。');
        } finally {
            setProcessing(null);
        }
    };

    const send = async (event) => {
        event.preventDefault();
        setProcessing('send');
        setError(null);

        try {
            const sent = await postJson(route('debug.contact.message', game.id), { message });

            if (sent) {
                setMessage('');
            }
        } catch {
            setError('処理に失敗しました。');
        } finally {
            setProcessing(null);
        }
    };

    const applyResource = async (event) => {
        event.preventDefault();
        setProcessing('resource');
        setError(null);

        try {
            await postJson(
                route('debug.contact.resource', game.id),
                { resource_remaining: Number(resourceDraft) },
                'PATCH',
            );
        } catch {
            setError('処理に失敗しました。');
        } finally {
            setProcessing(null);
        }
    };

    const remainingRatio = game?.resource_max
        ? Math.max(0, Math.min(1, game.resource_remaining / game.resource_max))
        : 0;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-sm font-medium text-indigo-600 dark:text-indigo-300">見知らぬ受信</p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        一文連絡
                    </h2>
                </div>
            }
        >
            <Head title="一文連絡" />

            <div className="bg-gradient-to-b from-slate-100 via-white to-white py-8 transition-colors dark:from-slate-950 dark:via-slate-950 dark:to-slate-950">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AppSectionTabs activeTab="contact" />

                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <p className="max-w-xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            あなたは、どの物語の登場人物でもない。一文を置くと、その場にいる見知らぬ誰かから連絡が届く。なぜ届くのかはわからない。返せるのは文章だけ。
                        </p>
                        <button
                            type="button"
                            onClick={toggleDebug}
                            aria-pressed={debugEnabled}
                            className={`rounded-lg px-3 py-2 text-xs font-semibold ${
                                debugEnabled
                                    ? 'bg-amber-500 text-amber-950'
                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                            }`}
                        >
                            デバッグ {debugEnabled ? 'オン' : 'オフ'}
                        </button>
                    </div>

                    {error && (
                        <p className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                            {error}
                        </p>
                    )}

                    {game && (
                        <section className="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-slate-500 dark:text-slate-400">
                                        見知らぬ送信者
                                    </p>
                                    <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                        {game.character_name}
                                    </h3>
                                </div>
                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    {STATUS_LABEL[game.status] ?? game.status}
                                </span>
                            </div>

                            {debugEnabled && debugState && (
                                <div className="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
                                    <p className="font-semibold">デバッグ</p>
                                    <div>
                                        <p>進捗 {debugState.progress} / 100</p>
                                        <div className="mt-1 h-2 overflow-hidden rounded-full bg-amber-200 dark:bg-amber-900">
                                            <div
                                                className="h-full bg-amber-500"
                                                style={{ width: `${Math.max(0, Math.min(100, debugState.progress))}%` }}
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <p className="font-medium">求めている回答</p>
                                        {debugState.useful_interventions?.length > 0 ? (
                                            <ul className="mt-1 list-disc pl-5">
                                                {debugState.useful_interventions.map((item) => (
                                                    <li key={item}>{item}</li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="mt-1">いまは特定の回答を求めていない。</p>
                                        )}
                                    </div>
                                    {debugState.misconception && (
                                        <p>誤解: {debugState.misconception}</p>
                                    )}
                                    {debugState.doom_if_ignored && (
                                        <p>尽きたとき: {debugState.doom_if_ignored}</p>
                                    )}
                                    <form onSubmit={applyResource} className="flex flex-wrap items-end gap-2">
                                        <label className="text-xs font-medium">
                                            残量
                                            <input
                                                type="number"
                                                min="0"
                                                max="99"
                                                value={resourceDraft}
                                                onChange={(event) => setResourceDraft(event.target.value)}
                                                disabled={processing}
                                                className="mt-1 block w-24 rounded-lg border-amber-300 bg-white text-sm text-slate-900 dark:border-amber-700 dark:bg-slate-950 dark:text-slate-100"
                                            />
                                        </label>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                        >
                                            {processing === 'resource' ? '反映しています…' : '残量を反映'}
                                        </button>
                                    </form>
                                    <p className="text-xs">信号が途切れたあとに残量を戻すと、同じ相手の続きから試せます。</p>
                                </div>
                            )}

                            <p className="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                置いた一文「{game.sentence}」
                            </p>
                            <p className="whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">
                                {game.situation}
                            </p>

                            <dl className="space-y-2 text-sm">
                                <div>
                                    <dt className="font-medium text-slate-500 dark:text-slate-400">届き方</dt>
                                    <dd className="text-slate-800 dark:text-slate-100">{game.channel}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-slate-500 dark:text-slate-400">途切れるまでにすること</dt>
                                    <dd className="text-slate-800 dark:text-slate-100">{game.clear_condition}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-slate-500 dark:text-slate-400">
                                        {game.resource_name} {game.resource_remaining} / {game.resource_max}
                                    </dt>
                                    <dd className="mt-1 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                        <div
                                            className="h-full bg-indigo-600"
                                            style={{ width: `${remainingRatio * 100}%` }}
                                        />
                                    </dd>
                                    <dd className="mt-1 text-slate-600 dark:text-slate-300">{game.resource_rule}</dd>
                                </div>
                            </dl>

                            {game.last_change_note && (
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    変化: {game.last_change_note}
                                </p>
                            )}

                            {game.status === 'lost' && game.loss_reason && (
                                <p className="text-sm font-medium text-rose-700 dark:text-rose-300">
                                    {game.loss_reason}
                                </p>
                            )}

                            {game.status === 'cleared' && (
                                <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                    向こうで、目処が立った。信号はもう急がなくていい。
                                </p>
                            )}

                            <ol className="space-y-3">
                                {game.messages.map((entry, index) => (
                                    <li
                                        key={`${entry.role}-${index}`}
                                        className={`rounded-xl px-4 py-3 text-sm leading-6 ${
                                            entry.role === 'player'
                                                ? 'bg-indigo-50 text-indigo-950 dark:bg-indigo-950/40 dark:text-indigo-100'
                                                : 'bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100'
                                        }`}
                                    >
                                        <p className="mb-1 text-xs font-semibold opacity-70">
                                            {entry.role === 'player' ? 'あなた（受信した本人）' : `${game.character_name}（見知らぬ相手）`}
                                        </p>
                                        <p className="whitespace-pre-wrap">{entry.text}</p>
                                    </li>
                                ))}
                            </ol>

                            {game.status === 'ongoing' && (
                                <form onSubmit={send} className="space-y-3">
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-200">
                                        見知らぬ相手へ返す
                                        <textarea
                                            value={message}
                                            onChange={(event) => setMessage(event.target.value)}
                                            rows={3}
                                            maxLength={1000}
                                            required
                                            disabled={processing}
                                            className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                        />
                                    </label>
                                    <button
                                        type="submit"
                                        disabled={processing || message.trim() === ''}
                                        className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                    >
                                        {processing === 'send' ? '返しています…' : '返す'}
                                    </button>
                                </form>
                            )}
                        </section>
                    )}

                    <form
                        onSubmit={start}
                        className="space-y-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"
                    >
                        <p className="text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {game
                                ? '別の一文を置くと、別の見知らぬ誰かから届く。'
                                : 'まだ誰からも届いていない。一文を置くと、受信が始まる。'}
                        </p>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-200">
                            受信する一文
                            <textarea
                                value={sentence}
                                onChange={(event) => setSentence(event.target.value)}
                                rows={3}
                                maxLength={500}
                                required
                                disabled={processing}
                                placeholder="漂流した宇宙飛行士が目を覚ますと、ここは異星だった。"
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            />
                        </label>
                        <button
                            type="submit"
                            disabled={processing || sentence.trim() === ''}
                            className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900"
                        >
                            {processing === 'start' ? '受信しています…' : '受信する'}
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
