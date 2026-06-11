import useTheme, { THEMES } from '@/Hooks/useTheme';

const THEME_META = {
    light: {
        label: 'ライト',
        short: 'LT',
        title: 'ライトテーマ',
    },
    dark: {
        label: 'ダーク',
        short: 'DK',
        title: 'ダークテーマ',
    },
    cyber: {
        label: 'CYBER',
        short: 'CB',
        title: 'サイバーパンクテーマ',
    },
};

function SunIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM16.364 3.636a.75.75 0 010 1.061l-1.06 1.06a.75.75 0 11-1.061-1.06l1.06-1.061a.75.75 0 011.061 0zM5.757 14.243a.75.75 0 010 1.06l-1.06 1.061a.75.75 0 11-1.061-1.06l1.06-1.061a.75.75 0 011.061 0zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM16.364 16.364a.75.75 0 01-1.06 0l-1.061-1.06a.75.75 0 111.06-1.061l1.061 1.06a.75.75 0 010 1.061zM5.757 5.757a.75.75 0 01-1.06 0l-1.061-1.06a.75.75 0 011.06-1.061l1.061 1.06a.75.75 0 010 1.061zM13.5 10a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z" />
        </svg>
    );
}

function MoonIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
                fillRule="evenodd"
                d="M7.455 2.004a.75.75 0 01.26.77 7.504 7.504 0 009.51 9.51.75.75 0 01.77.26A8.75 8.75 0 117.455 2.004z"
                clipRule="evenodd"
            />
        </svg>
    );
}

function CyberIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fillRule="evenodd" d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105l-1.732 2.308a.75.75 0 01-1.2 0L7.855 15H4.25A2.25 2.25 0 012 12.75v-8.5zm2.25-.75a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h4.105a1 1 0 01.8.4l1.045 1.393 1.045-1.393a1 1 0 01.8-.4H15.75a.75.75 0 00.75-.75v-8.5a.75.75 0 00-.75-.75H4.25zM6.5 6.75a.75.75 0 000 1.5h7a.75.75 0 000-1.5h-7zm0 3a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5h-4.5z" clipRule="evenodd" />
        </svg>
    );
}

const THEME_ICONS = {
    light: SunIcon,
    dark: MoonIcon,
    cyber: CyberIcon,
};

export default function ThemeToggle({ className = '' }) {
    const { theme, setTheme } = useTheme();

    return (
        <div
            className={`inline-flex items-center rounded-full border border-slate-200 bg-white p-0.5 shadow-sm dark:border-slate-700 dark:bg-slate-900 ${className}`}
            role="group"
            aria-label="テーマ切り替え"
        >
            {THEMES.map((themeId) => {
                const Icon = THEME_ICONS[themeId];
                const meta = THEME_META[themeId];
                const isActive = theme === themeId;

                return (
                    <button
                        key={themeId}
                        type="button"
                        onClick={() => setTheme(themeId)}
                        title={meta.title}
                        aria-pressed={isActive}
                        aria-label={meta.title}
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1.5 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950 ${
                            isActive
                                ? themeId === 'cyber'
                                    ? 'bg-[#0f0f1a] text-[#00ff9f] shadow-[0_0_12px_rgba(0,255,159,0.35)] ring-1 ring-[#00ff9f]/40'
                                    : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-200'
                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                        }`}
                    >
                        <Icon className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">{meta.label}</span>
                        <span className="sm:hidden">{meta.short}</span>
                    </button>
                );
            })}
        </div>
    );
}
