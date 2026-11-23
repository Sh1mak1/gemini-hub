import AppSectionTabs from '@/Components/AppSectionTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function csrfHeaders() {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
    };
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (meta) {
        headers['X-CSRF-TOKEN'] = meta;
        return headers;
    }

    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    if (match) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
    }

    return headers;
}

const STATUS_LABEL = {
    ongoing: '信号は続いている',
    cleared: '向こうで目処が立った',
    lost: '信号が途切れた',
};

function senderLabel(game) {
    return game.character_role || game.character_name;
}

export default function Contact({ game: initialGame, llmProvider: initialProvider = null }) {
    const canDebug = usePage().props.auth?.user?.role !== 'guest';
    const [game, setGame] = useState(initialGame);
    const [sentence, setSentence] = useState('');
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(null);
    const [error, setError] = useState(null);
    const [debugEnabled, setDebugEnabled] = useState(false);
    const [debugState, setDebugState] = useState(null);
    const [resourceDraft, setResourceDraft] = useState(initialGame?.resource_remaining ?? 0);
    const [sendFailure, setSendFailure] = useState(null);
    const [composing, setComposing] = useState(!initialGame);
    const [hubOpen, setHubOpen] = useState(false);
    const [llmProvider, setLlmProvider] = useState(initialProvider);
    const debugEnabledRef = useRef(false);

    useEffect(() => {
        if (!canDebug) {
            debugEnabledRef.current = false;
            setDebugEnabled(false);
            return;
        }

        try {
            const enabled = window.localStorage.getItem('contact-debug-mode') === '1';
            debugEnabledRef.current = enabled;
            setDebugEnabled(enabled);
        } catch {
            debugEnabledRef.current = false;
        }
    }, [canDebug]);

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
            ...csrfHeaders(),
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
        const errorMessage = payload.error
            ?? payload.errors?.sentence?.[0]
            ?? payload.errors?.message?.[0]
            ?? payload.errors?.resource_remaining?.[0]
            ?? '処理に失敗しました。';

        if (payload.game) {
            setGame(payload.game);
        }

        if (payload.debug) {
            setDebugState(payload.debug);
        }

        if (response.status === 422 || !response.ok) {
            return {
                ok: false,
                retryable: payload.retryable === true || (!response.ok && response.status !== 422),
                error: errorMessage,
            };
        }

        if (payload.error) {
            return { ok: false, retryable: false, error: payload.error };
        }

        return { ok: true, retryable: false, error: null };
    };

    const start = async (event) => {
        event.preventDefault();
        setProcessing('start');
        setError(null);

        try {
            const started = await postJson(route('debug.contact.start'), { sentence });

            if (started.ok) {
                setSentence('');
                setMessage('');
                setSendFailure(null);
                setComposing(false);
            } else {
                setError(started.error);
            }
        } catch {
            setError('処理に失敗しました。');
        } finally {
            setProcessing(null);
        }
    };

    const submitMessage = async (text) => {
        setProcessing('send');
        setError(null);

        try {
            const updated = await postJson(route('debug.contact.message', game.id), { message: text });

            if (updated.ok) {
                setMessage('');
                setSendFailure(null);

                return;
            }

            if (updated.retryable) {
                setSendFailure({ message: updated.error, text });

                return;
            }

            setSendFailure(null);
            setError(updated.error);
        } catch {
            setSendFailure({
                message: 'Gemini との通信に失敗しました。しばらくしてからもう一度送ってください。',
                text,
            });
        } finally {
            setProcessing(null);
        }
    };

    const send = (event) => {
        event.preventDefault();
        submitMessage(message);
    };

    const retrySend = () => {
        if (!sendFailure) {
            return;
        }

        submitMessage(sendFailure.text);
    };

    const applyResource = async (event) => {
        event.preventDefault();
        setProcessing('resource');
        setError(null);

        try {
            const updated = await postJson(
                route('debug.contact.resource', game.id),
                { resource_remaining: Number(resourceDraft) },
                'PATCH',
            );

            if (!updated.ok) {
                setError(updated.error);
            }
        } catch {
            setError('処理に失敗しました。');
        } finally {
            setProcessing(null);
        }
    };

    const resetSignal = async () => {
        if (!game || processing) {
            return;
        }

        if (!window.confirm('この信号を消して、最初の画面に戻ります。')) {
            return;
        }

        setProcessing('reset');
        setError(null);

        try {
            const response = await fetch(route('debug.contact.reset', game.id), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                setError('この信号を消せませんでした。');

                return;
            }

            setGame(null);
            setDebugState(null);
            setMessage('');
            setSendFailure(null);
            setComposing(true);
        } catch {
            setError('この信号を消せませんでした。');
        } finally {
            setProcessing(null);
        }
    };

    const switchProvider = async (nextProvider) => {
        if (!canDebug || nextProvider === llmProvider || processing) {
            return;
        }

        setProcessing('provider');
        setError(null);

        try {
            const response = await fetch(route('debug.contact.provider'), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ provider: nextProvider }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                setError(payload.error ?? 'APIを切り替えられませんでした。');
                return;
            }

            setLlmProvider(payload.provider ?? nextProvider);
        } catch {
            setError('APIを切り替えられませんでした。');
        } finally {
            setProcessing(null);
        }
    };

    const remainingRatio = game?.resource_max
        ? Math.max(0, Math.min(1, game.resource_remaining / game.resource_max))
        : 0;

    return (
        <AuthenticatedLayout>
            <Head title="Stray Signal" />

            <div className="min-h-[calc(100vh-4rem)] bg-[#14120e] text-[#f4efe4]">
                <div className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                    {hubOpen && <AppSectionTabs activeTab="contact" />}

                    <div className="flex items-center justify-between gap-3">
                        <button
                            type="button"
                            onClick={() => setHubOpen((open) => !open)}
                            aria-expanded={hubOpen}
                            className="text-xs tracking-[0.18em] text-[#b7ab96]"
                        >
                            {hubOpen ? 'メニューを閉じる' : 'メニュー'}
                        </button>
                        {canDebug && (
                            <div className="flex items-center gap-2">
                                <div className="flex rounded-full bg-[#2a241c] p-0.5" role="group" aria-label="連絡のAPI">
                                    {[
                                        ['gemini', 'Gemini'],
                                        ['openrouter', 'OpenRouter'],
                                    ].map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => switchProvider(value)}
                                            aria-pressed={llmProvider === value}
                                            disabled={processing === 'provider'}
                                            className={`rounded-full px-3 py-1 text-[11px] font-semibold tracking-wide ${
                                                llmProvider === value
                                                    ? 'bg-[#e0b15a] text-[#241c10]'
                                                    : 'text-[#b7ab96]'
                                            }`}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                <button
                                    type="button"
                                    onClick={toggleDebug}
                                    aria-pressed={debugEnabled}
                                    className={`rounded-full px-3 py-1 text-[11px] font-semibold tracking-wide ${
                                        debugEnabled
                                            ? 'bg-[#e0b15a] text-[#241c10]'
                                            : 'bg-[#2a241c] text-[#b7ab96]'
                                    }`}
                                >
                                    デバッグ {debugEnabled ? 'オン' : 'オフ'}
                                </button>
                            </div>
                        )}
                    </div>

                    {error && (
                        <p className="rounded-2xl border border-[#8a4a3d] bg-[#2a1814] px-4 py-3 text-sm text-[#f0c7bc]">
                            {error}
                        </p>
                    )}

                    {composing && (
                        <section className="mx-auto max-w-2xl pb-10 pt-4 sm:pt-10">
                            <p className="text-[11px] font-semibold tracking-[0.42em] text-[#e0b15a]">
                                A CONTACT GAME
                            </p>
                            <h1 className="mt-4 font-serif text-5xl leading-none text-[#f7f1e6] sm:text-7xl">
                                Stray Signal
                            </h1>
                            <p className="mt-8 max-w-xl text-base leading-8 text-[#d9d0c0]">
                                あなたは、どの物語の登場人物でもない。一文を置くと、その場にいる見知らぬ誰かから連絡が届く。なぜ届くのかはわからない。返せるのは文章だけ。
                            </p>
                            <ul className="mt-8 space-y-3 text-sm leading-7 text-[#b7ab96]">
                                <li>相手は名前ではなく、飛行機操縦士のような属性で現れる。</li>
                                <li>「試してくれ」と頼めば、相手はそれをやってから結果を返す。</li>
                                <li>残っているものが尽きる前に、示されたことを成し遂げる。</li>
                            </ul>
                            <form onSubmit={start} className="mt-10 space-y-4">
                                <label className="block text-sm text-[#d9d0c0]">
                                    置く一文
                                    <textarea
                                        value={sentence}
                                        onChange={(event) => setSentence(event.target.value)}
                                        rows={4}
                                        maxLength={500}
                                        required
                                        disabled={processing}
                                        placeholder="漂流した宇宙飛行士が目を覚ますと、ここは異星だった。"
                                        className="mt-2 w-full rounded-2xl border border-[#3a3328] bg-[#1c1914] px-4 py-3 text-base leading-7 text-[#f4efe4] placeholder:text-[#7d7364] focus:border-[#e0b15a] focus:ring-[#e0b15a]"
                                    />
                                </label>
                                <div className="flex flex-wrap items-center gap-4">
                                    <button
                                        type="submit"
                                        disabled={processing || sentence.trim() === ''}
                                        className="rounded-full bg-[#e0b15a] px-6 py-3 text-sm font-semibold text-[#241c10] disabled:opacity-40"
                                    >
                                        {processing === 'start' ? '信号を待っています…' : '信号を開く'}
                                    </button>
                                    {game && (
                                        <button
                                            type="button"
                                            onClick={() => setComposing(false)}
                                            className="text-sm text-[#b7ab96] underline decoration-[#3a3328] underline-offset-4"
                                        >
                                            いまの信号に戻る
                                        </button>
                                    )}
                                </div>
                            </form>
                        </section>
                    )}

                    {!composing && game && (
                        <section className="space-y-6 rounded-[28px] border border-[#3a3328] bg-[#1c1914] p-4 sm:p-7">
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <p className="font-serif text-sm tracking-[0.28em] text-[#e0b15a]">Stray Signal</p>
                                    <h2 className="mt-2 font-serif text-3xl text-[#f7f1e6]">{senderLabel(game)}</h2>
                                </div>
                                <span className="rounded-full border border-[#3a3328] px-3 py-1 text-xs text-[#d9d0c0]">
                                    {STATUS_LABEL[game.status] ?? game.status}
                                </span>
                            </div>

                            {debugEnabled && debugState && (
                                <div className="space-y-3 rounded-2xl border border-[#6d5428] bg-[#2a2216] p-3 text-sm text-[#f0e2c4]">
                                    <p className="font-semibold">デバッグ</p>
                                    <div>
                                        <p>進捗 {debugState.progress} / 100</p>
                                        <div className="mt-1 h-2 overflow-hidden rounded-full bg-[#3a3328]">
                                            <div
                                                className="h-full bg-[#e0b15a]"
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
                                                className="mt-1 block w-24 rounded-lg border-[#6d5428] bg-[#14120e] text-sm text-[#f4efe4]"
                                            />
                                        </label>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="rounded-full bg-[#e0b15a] px-3 py-2 text-xs font-semibold text-[#241c10] disabled:opacity-40"
                                        >
                                            {processing === 'resource' ? '反映しています…' : '残量を反映'}
                                        </button>
                                    </form>
                                    <p className="text-xs text-[#b7ab96]">信号が途切れたあとに残量を戻すと、同じ相手の続きから試せます。</p>
                                </div>
                            )}

                            <p className="text-sm leading-7 text-[#b7ab96]">置いた一文「{game.sentence}」</p>
                            <p className="whitespace-pre-wrap text-sm leading-7 text-[#f4efe4]">{game.situation}</p>

                            <dl className="space-y-3 text-sm">
                                <div>
                                    <dt className="text-xs tracking-wide text-[#e0b15a]">届き方</dt>
                                    <dd className="mt-1 leading-6 text-[#f4efe4]">{game.channel}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs tracking-wide text-[#e0b15a]">途切れるまでにすること</dt>
                                    <dd className="mt-1 leading-6 text-[#f4efe4]">{game.clear_condition}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs tracking-wide text-[#e0b15a]">
                                        {game.resource_name} {game.resource_remaining} / {game.resource_max}
                                    </dt>
                                    <dd className="mt-2 h-1.5 overflow-hidden rounded-full bg-[#3a3328]">
                                        <div
                                            className="h-full bg-[#e0b15a]"
                                            style={{ width: `${remainingRatio * 100}%` }}
                                        />
                                    </dd>
                                    <dd className="mt-2 leading-6 text-[#b7ab96]">{game.resource_rule}</dd>
                                </div>
                            </dl>

                            {game.last_change_note && (
                                <p className="text-sm leading-6 text-[#d9d0c0]">変化: {game.last_change_note}</p>
                            )}

                            {game.status === 'lost' && game.loss_reason && (
                                <p className="text-sm font-medium leading-6 text-[#f0c7bc]">{game.loss_reason}</p>
                            )}

                            {game.status === 'cleared' && (
                                <div
                                    role="status"
                                    className="space-y-2 rounded-2xl border border-[#3f6b52] bg-[#17261e] p-4 text-[#d7f5e4]"
                                >
                                    <p className="font-serif text-2xl">クリア</p>
                                    <p>おめでとう。向こうで、目処が立った。</p>
                                    {game.ending && (
                                        <p className="whitespace-pre-wrap leading-7">{game.ending}</p>
                                    )}
                                </div>
                            )}

                            <ol className="space-y-5 border-l border-[#3a3328] pl-4">
                                {game.messages.map((entry, index) => {
                                    const fromPlayer = entry.role === 'player';

                                    return (
                                        <li key={`${entry.role}-${index}`} className={fromPlayer ? 'pl-8' : ''}>
                                            <p className={`text-[11px] tracking-[0.16em] ${fromPlayer ? 'text-right text-[#8fbf9f]' : 'text-[#e0b15a]'}`}>
                                                {fromPlayer ? 'あなた' : senderLabel(game)}
                                            </p>
                                            <p className={`mt-1 whitespace-pre-wrap text-sm leading-7 ${
                                                fromPlayer
                                                    ? 'ml-auto max-w-[85%] border-l border-[#8fbf9f] pl-3 text-right text-[#d7f5e4]'
                                                    : 'text-[#f4efe4]'
                                            }`}
                                            >
                                                {entry.text}
                                            </p>
                                        </li>
                                    );
                                })}
                            </ol>

                            {game.status === 'ongoing' && (
                                <form onSubmit={send} className="flex items-end gap-3 border-t border-[#3a3328] pt-4">
                                    <label className="block min-w-0 flex-1 text-sm text-[#d9d0c0]">
                                        見知らぬ相手へ返す
                                        <textarea
                                            value={message}
                                            onChange={(event) => setMessage(event.target.value)}
                                            rows={2}
                                            maxLength={1000}
                                            required
                                            disabled={processing}
                                            className="mt-2 w-full resize-none border-0 border-b border-[#3a3328] bg-transparent px-0 py-2 text-sm leading-7 text-[#f4efe4] focus:border-[#e0b15a] focus:ring-0"
                                        />
                                    </label>
                                    <button
                                        type="submit"
                                        disabled={processing || message.trim() === ''}
                                        className="mb-1 shrink-0 rounded-full bg-[#e0b15a] px-4 py-2 text-sm font-semibold text-[#241c10] disabled:opacity-40"
                                    >
                                        {processing === 'send' ? '送信中' : '返す'}
                                    </button>
                                </form>
                            )}

                            <div className="flex flex-wrap gap-x-5 gap-y-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setError(null);
                                        setComposing(true);
                                    }}
                                    className="text-sm text-[#b7ab96] underline decoration-[#3a3328] underline-offset-4"
                                >
                                    別の一文を置く
                                </button>
                                <button
                                    type="button"
                                    onClick={resetSignal}
                                    disabled={processing === 'reset'}
                                    className="text-sm text-[#f0c7bc] underline decoration-[#8a4a3d] underline-offset-4 disabled:opacity-40"
                                >
                                    {processing === 'reset' ? '消しています…' : 'この信号を消す'}
                                </button>
                            </div>
                        </section>
                    )}
                </div>
            </div>
            {sendFailure && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="send-failure-title"
                        className="w-full max-w-md space-y-4 rounded-[28px] border border-[#3a3328] bg-[#1c1914] p-6 text-[#f4efe4] shadow-2xl"
                    >
                        <div>
                            <h3 id="send-failure-title" className="font-serif text-2xl">
                                送信できませんでした
                            </h3>
                            <p className="mt-3 text-sm leading-7 text-[#d9d0c0]">{sendFailure.message}</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={retrySend}
                                disabled={processing === 'send'}
                                className="rounded-full bg-[#e0b15a] px-4 py-2 text-sm font-semibold text-[#241c10] disabled:opacity-40"
                            >
                                {processing === 'send' ? '返しています…' : 'もう一度送る'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setSendFailure(null)}
                                disabled={processing === 'send'}
                                className="rounded-full border border-[#3a3328] px-4 py-2 text-sm text-[#d9d0c0] disabled:opacity-40"
                            >
                                閉じる
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
