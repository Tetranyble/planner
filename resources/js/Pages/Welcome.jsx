import { Button } from '@/Components/ui/button';
import ThemeSwitcher from '@/theme/ThemeSwitcher';
import { Head, Link } from '@inertiajs/react';
import { CalendarRange, Layers, ShieldCheck, Workflow } from 'lucide-react';

const capabilities = [
    { title: 'Multiple brands', description: 'Organise marketing activities across separate brand workspaces.', Icon: Layers },
    { title: 'Campaign workflows', description: 'Plan campaigns and move content through explicit approval stages.', Icon: Workflow },
    { title: 'Governed access', description: 'Keep account access and publishing permissions within your organisation.', Icon: ShieldCheck },
];

export default function Welcome({ auth, canLogin = true, canRegister = false }) {
    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title="Welcome" />
            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-3 px-5 py-4 sm:px-8">
                    <div className="flex items-center gap-2 font-semibold tracking-tight"><CalendarRange className="size-6 text-primary" aria-hidden="true" />Planner</div>
                    <div className="flex items-center gap-3">
                        <ThemeSwitcher />
                        {auth?.user ? (
                            <Button asChild><Link href={route('dashboard')}>Dashboard</Link></Button>
                        ) : canLogin ? (
                            <Button variant="outline" asChild><Link href={route('login')}>Log in</Link></Button>
                        ) : null}
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-7xl px-5 py-16 sm:px-8 md:py-24">
                <div className="max-w-3xl">
                    <span className="inline-flex rounded-full bg-brand-soft px-3 py-1 text-sm font-medium text-on-brand-soft">Multi-brand marketing workspace</span>
                    <h1 className="mt-6 max-w-2xl text-4xl font-semibold tracking-tight sm:text-5xl">Manage every brand from one marketing workspace.</h1>
                    <p className="mt-5 max-w-2xl text-lg text-muted-foreground">Planner brings marketing teams, campaigns, and governed publishing workflows together in a single application.</p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        {auth?.user ? <Button asChild><Link href={route('organisations.index')}>My organisations</Link></Button> : canLogin ? <Button asChild><Link href={route('login')}>Access Planner</Link></Button> : null}
                        {!auth?.user && canRegister && <Button variant="outline" asChild><Link href={route('register')}>Create account</Link></Button>}
                    </div>
                </div>
                <div className="mt-16 grid gap-4 md:grid-cols-3">
                    {capabilities.map(({ title, description, Icon }) => (
                        <div key={title} className="rounded-2xl border border-border bg-card p-6 text-card-foreground shadow-card">
                            <div className="mb-5 inline-flex size-10 items-center justify-center rounded-xl bg-brand-soft text-on-brand-soft"><Icon className="size-5" aria-hidden="true" /></div>
                            <h2 className="text-lg font-semibold">{title}</h2>
                            <p className="mt-2 text-sm text-muted-foreground">{description}</p>
                        </div>
                    ))}
                </div>
            </main>
        </div>
    );
}
