import ApplicationLogo from '@/Components/ApplicationLogo';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-slate-100 pt-6 text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100 sm:justify-center sm:pt-0">
            <div className="absolute right-4 top-4">
                <ThemeToggle />
            </div>

            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-slate-500 dark:text-slate-300" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md transition-colors dark:bg-slate-900 dark:shadow-slate-950/40 sm:max-w-md sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}
