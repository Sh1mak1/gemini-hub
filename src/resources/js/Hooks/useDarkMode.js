import { useEffect, useState } from 'react';

const STORAGE_KEY = 'theme';
const THEME_CHANGE_EVENT = 'themechange';

function getStoredTheme() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const storedTheme = window.localStorage.getItem(STORAGE_KEY);

        return storedTheme === 'dark' || storedTheme === 'light'
            ? storedTheme
            : null;
    } catch {
        return null;
    }
}

function getInitialTheme() {
    if (typeof document === 'undefined') {
        return 'light';
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

export default function useDarkMode() {
    const [theme, setTheme] = useState(getInitialTheme);

    useEffect(() => {
        applyTheme(theme);

        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch {
            // Ignore storage failures; the current page still reflects the selected theme.
        }
    }, [theme]);

    useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const syncSystemTheme = () => {
            if (getStoredTheme() === null) {
                setTheme(mediaQuery.matches ? 'dark' : 'light');
            }
        };

        mediaQuery.addEventListener('change', syncSystemTheme);

        return () => mediaQuery.removeEventListener('change', syncSystemTheme);
    }, []);

    useEffect(() => {
        const syncTheme = (event) => {
            setTheme(event.detail.theme);
        };
        const syncStoredTheme = () => {
            setTheme(getStoredTheme() ?? getSystemTheme());
        };

        window.addEventListener(THEME_CHANGE_EVENT, syncTheme);
        window.addEventListener('storage', syncStoredTheme);

        return () => {
            window.removeEventListener(THEME_CHANGE_EVENT, syncTheme);
            window.removeEventListener('storage', syncStoredTheme);
        };
    }, []);

    return {
        isDark: theme === 'dark',
        theme,
        toggleTheme: () => {
            setTheme((currentTheme) => {
                const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

                window.dispatchEvent(
                    new CustomEvent(THEME_CHANGE_EVENT, {
                        detail: { theme: nextTheme },
                    }),
                );

                return nextTheme;
            });
        },
    };
}
