/**
 * Appearance preferences are application-wide, not marketing brand settings.
 * Do not serialize or infer the user's preference from organisation/brand data.
 */
export const THEME_COOKIE = 'planner_theme';
export const THEME_VALUES = Object.freeze(['light', 'dark', 'system']);

export function normalizePreference(value) {
    return THEME_VALUES.includes(value) ? value : 'system';
}

export function readThemeCookie(cookieString = '') {
    if (typeof cookieString !== 'string') return 'system';
    const part = cookieString.split(';').map((item) => item.trim()).find((item) => item.startsWith(`${THEME_COOKIE}=`));
    if (!part) return 'system';
    try {
        return normalizePreference(decodeURIComponent(part.slice(THEME_COOKIE.length + 1)));
    } catch {
        return 'system';
    }
}

export function resolveTheme(preference, prefersDark = false) {
    const value = normalizePreference(preference);
    return value === 'system' ? (prefersDark ? 'dark' : 'light') : value;
}

export function buildThemeCookie(preference, secure = false) {
    const value = normalizePreference(preference);
    return `${THEME_COOKIE}=${encodeURIComponent(value)}; Max-Age=31536000; Path=/; SameSite=Lax${secure ? '; Secure' : ''}`;
}

export function applyTheme(root, preference, prefersDark = false) {
    const resolved = resolveTheme(preference, prefersDark);
    root.classList.toggle('dark', resolved === 'dark');
    root.dataset.theme = resolved;
    root.dataset.themePreference = normalizePreference(preference);
    root.style.colorScheme = resolved;
    return resolved;
}
