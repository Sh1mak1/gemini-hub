import { useEffect, useState } from 'react';

export const THEMES = ['light', 'dark', 'cyber'];

const STORAGE_KEY = 'theme';
const THEME_CHANGE_EVENT = 'themechange';

function getStoredTheme() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const storedTheme = window.localStorage.getItem(STORAGE_KEY);

        return THEMES.includes(storedTheme) ? storedTheme : null;
    } catch {
        return null;
    }
}

function getInitialTheme() {
    if (typeof document === 'undefined') {
        return 'light';
    }

    const dataTheme = document.documentElement.getAttribute('data-theme');

    if (THEMES.includes(dataTheme)) {
        return dataTheme;
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

export function applyTheme(theme) {
    const root = document.documentElement;

    root.setAttribute('data-theme', theme);
    root.classList.toggle('dark', theme === 'dark');
}

export default function useTheme() {
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

    const setThemeWithSync = (nextTheme) => {
        setTheme(nextTheme);
        window.dispatchEvent(
            new CustomEvent(THEME_CHANGE_EVENT, {
                detail: { theme: nextTheme },
            }),
        );
    };

    const cycleTheme = () => {
        const index = THEMES.indexOf(theme);
        const nextTheme = THEMES[(index + 1) % THEMES.length];
        setThemeWithSync(nextTheme);
    };

    return {
        theme,
        isDark: theme === 'dark',
        isCyber: theme === 'cyber',
        setTheme: setThemeWithSync,
        cycleTheme,
    };
}
