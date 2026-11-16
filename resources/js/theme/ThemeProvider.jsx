import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { applyTheme, buildThemeCookie, normalizePreference, readThemeCookie, resolveTheme } from './config';

const ThemeContext = createContext(null);

const prefersDark = () => window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;

export function ThemeProvider({ children }) {
    const [preference, setPreferenceState] = useState(() => readThemeCookie(document.cookie));
    const [resolvedTheme, setResolvedTheme] = useState(() => resolveTheme(readThemeCookie(document.cookie), prefersDark()));

    useEffect(() => {
        const media = window.matchMedia?.('(prefers-color-scheme: dark)');
        const syncTheme = () => setResolvedTheme(applyTheme(document.documentElement, preference, media?.matches ?? false));
        syncTheme();
        media?.addEventListener?.('change', syncTheme);
        return () => media?.removeEventListener?.('change', syncTheme);
    }, [preference]);

    const setTheme = useCallback((next) => {
        const safe = normalizePreference(next);
        document.cookie = buildThemeCookie(safe, window.location.protocol === 'https:');
        setPreferenceState(safe);
        // Apply immediately, before React renders, to avoid flashing the previous theme.
        setResolvedTheme(applyTheme(document.documentElement, safe, prefersDark()));
    }, []);

    const context = useMemo(() => ({ theme: preference, resolvedTheme, setTheme }), [preference, resolvedTheme, setTheme]);
    return <ThemeContext.Provider value={context}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (!context) throw new Error('useTheme must be used inside ThemeProvider');
    return context;
}
