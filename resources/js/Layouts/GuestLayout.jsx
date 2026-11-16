import ApplicationLogo from '@/Components/ApplicationLogo';
import ThemeSwitcher from '@/theme/ThemeSwitcher';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center bg-background px-4 py-10 text-foreground">
            <div className="absolute right-4 top-4 sm:right-8 sm:top-6"><ThemeSwitcher /></div>
            <Link href="/" className="mb-6 flex items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-ring">
                <ApplicationLogo className="size-10 fill-current text-primary" />
                <span className="text-xl font-semibold tracking-tight">Planner</span>
            </Link>
            <div className="w-full max-w-md rounded-2xl border border-border bg-card px-6 py-7 text-card-foreground shadow-card sm:px-8">
                {children}
            </div>
        </div>
    );
}
