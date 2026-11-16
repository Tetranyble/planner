import { Link } from '@inertiajs/react';

export default function NavLink({ active = false, className = '', children, ...props }) {
    return (
        <Link
            {...props}
            aria-current={active ? 'page' : undefined}
            className={`inline-flex h-16 items-center border-b-2 px-1 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-ring ${active ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'} ${className}`}
        >
            {children}
        </Link>
    );
}
