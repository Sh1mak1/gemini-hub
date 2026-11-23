import AppSectionTabs from '@/Components/AppSectionTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

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

    const postJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
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

                    <p className="text-sm leading-6 text-slate-600 dark:text-slate-300">
                        あなたは、どの物語の登場人物でもない。一文を置くと、その場にいる見知らぬ誰かから連絡が届く。なぜ届くのかはわからない。返せるのは文章だけ。
                    </p>

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
