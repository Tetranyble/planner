import { Monitor, Moon, Sun } from 'lucide-react';
import { useTheme } from './ThemeProvider';

const choices = [
    { value: 'light', label: 'Light', Icon: Sun },
    { value: 'dark', label: 'Dark', Icon: Moon },
    { value: 'system', label: 'System', Icon: Monitor },
];

/** Accessible, lightweight theme selector — no extra popover dependency. */
export default function ThemeSwitcher({ className = '' }) {
    const { theme, setTheme } = useTheme();
    return (
        <div role="group" aria-label="Appearance" className={`inline-flex items-center gap-0.5 rounded-lg border border-border bg-muted p-1 ${className}`}>
            {choices.map(({ value, label, Icon }) => (
                <button
                    key={value}
                    type="button"
                    aria-label={`${label} appearance`}
                    aria-pressed={theme === value}
                    title={`${label} appearance`}
                    onClick={() => setTheme(value)}
                    className={`inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-ring ${theme === value ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:bg-card/60 hover:text-foreground'}`}
                >
                    <Icon className="size-4" aria-hidden="true" />
                    <span className="sr-only">{label}</span>
                </button>
            ))}
        </div>
    );
}
