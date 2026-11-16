import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({ active = false, className = '', children, ...props }) {
    return (
        <Link
            {...props}
            aria-current={active ? 'page' : undefined}
            className={`flex w-full items-center rounded-md border-s-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-ring ${active ? 'border-primary bg-brand-soft text-on-brand-soft' : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground'} ${className}`}
        >
            {children}
        </Link>
    );
}
