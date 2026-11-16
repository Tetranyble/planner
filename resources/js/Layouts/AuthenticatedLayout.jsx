import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeSwitcher from '@/theme/ThemeSwitcher';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const user = page.props.auth.user;
    const flash = page.props.flash ?? {};
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    return (
        <div className="min-h-screen bg-background text-foreground">
            <nav className="border-b border-border bg-card" aria-label="Main navigation">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between gap-4">
                        <div className="flex min-w-0 items-center gap-5 sm:gap-8">
                            <Link href={route('dashboard')} className="flex shrink-0 items-center gap-2 rounded-md focus-visible:outline-2 focus-visible:outline-ring" aria-label="Planner dashboard">
                                <ApplicationLogo className="block size-8 fill-current text-primary" />
                                <span className="text-base font-semibold tracking-tight">Planner</span>
                            </Link>
                            <div className="hidden h-full items-center gap-5 sm:flex">
                                <NavLink href={route('dashboard')} active={route().current('dashboard')}>Dashboard</NavLink>
                                <NavLink href={route('organisations.index')} active={route().current('organisations.*')}>Organisations</NavLink>
                            </div>
                        </div>
                        <div className="hidden items-center gap-3 sm:flex">
                            <ThemeSwitcher />
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button type="button" className="inline-flex items-center rounded-lg border border-border bg-card px-3 py-2 text-sm font-medium text-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring">
                                        {user.name}
                                        <svg className="ms-2 size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" /></svg>
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                    <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                        <button type="button" aria-label="Toggle navigation" aria-controls="planner-mobile-menu" aria-expanded={showingNavigationDropdown} onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)} className="inline-flex size-10 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring sm:hidden">
                            {showingNavigationDropdown ? <X className="size-5" aria-hidden="true" /> : <Menu className="size-5" aria-hidden="true" />}
                        </button>
                    </div>
                </div>
                {showingNavigationDropdown && (
                    <div id="planner-mobile-menu" className="border-t border-border px-4 py-3 sm:hidden">
                        <div className="space-y-1">
                            <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>Dashboard</ResponsiveNavLink>
                            <ResponsiveNavLink href={route('organisations.index')} active={route().current('organisations.*')}>Organisations</ResponsiveNavLink>
                        </div>
                        <div className="mt-3 border-t border-border pt-3">
                            <p className="mb-1 text-sm font-medium text-foreground">{user.name}</p>
                            <p className="mb-3 text-xs text-muted-foreground">{user.email}</p>
                            <ThemeSwitcher />
                            <div className="mt-3 space-y-1">
                                <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                                <ResponsiveNavLink href={route('logout')} method="post" as="button">Log Out</ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                )}
            </nav>
            {header && <header className="border-b border-border bg-card"><div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{header}</div></header>}
            <main id="main-content">
                {flash.success && <div role="status" className="mx-auto mt-5 max-w-7xl rounded-lg border border-border bg-card px-5 py-3 text-sm text-foreground" aria-live="polite">{flash.success}</div>}
                {flash.error && <div role="alert" className="mx-auto mt-5 max-w-7xl rounded-lg border border-destructive bg-card px-5 py-3 text-sm text-destructive">{flash.error}</div>}
                {children}
            </main>
        </div>
    );
}
