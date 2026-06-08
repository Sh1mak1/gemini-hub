import useDarkMode from '@/Hooks/useDarkMode';

export default function ThemeToggle({ className = '' }) {
    const { isDark, toggleTheme } = useDarkMode();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-pressed={isDark}
            aria-label={isDark ? 'ライトモードに切り替え' : 'ダークモードに切り替え'}
            className={`inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-500/70 dark:hover:bg-slate-800 dark:hover:text-indigo-200 dark:focus:ring-offset-slate-950 ${className}`}
        >
            {isDark ? (
                <svg
                    className="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM16.364 3.636a.75.75 0 010 1.061l-1.06 1.06a.75.75 0 11-1.061-1.06l1.06-1.061a.75.75 0 011.061 0zM5.757 14.243a.75.75 0 010 1.06l-1.06 1.061a.75.75 0 11-1.061-1.06l1.06-1.061a.75.75 0 011.061 0zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM16.364 16.364a.75.75 0 01-1.06 0l-1.061-1.06a.75.75 0 111.06-1.061l1.061 1.06a.75.75 0 010 1.061zM5.757 5.757a.75.75 0 01-1.06 0l-1.061-1.06a.75.75 0 011.06-1.061l1.061 1.06a.75.75 0 010 1.061zM13.5 10a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z" />
                </svg>
            ) : (
                <svg
                    className="h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fillRule="evenodd"
                        d="M7.455 2.004a.75.75 0 01.26.77 7.504 7.504 0 009.51 9.51.75.75 0 01.77.26A8.75 8.75 0 117.455 2.004z"
                        clipRule="evenodd"
                    />
                </svg>
            )}
            <span>{isDark ? 'ライト' : 'ダーク'}</span>
        </button>
    );
}
