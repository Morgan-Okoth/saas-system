<?jsx
import { Head, Link } from '@inertiajs/react';

export default function AuthenticatedLayout({ user, children, title }) {
    return (
        <div className="min-h-screen bg-gray-100">
            <Head title={title} />
            <nav className="bg-white border-b border-gray-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <Link href="/dashboard">
                                    <span className="text-xl font-bold text-gray-800 cursor-pointer">
                                        SaaS School System
                                    </span>
                                </Link>
                            </div>
                        </div>
                        <div className="flex items-center">
                            <div className="ml-3 text-sm text-gray-600 mr-4">
                                {user?.school?.name}
                            </div>
                            <div className="ml-3 text-sm text-gray-600">
                                {user?.email}
                            </div>
                            <div className="ml-3">
                                <Link 
                                    href="/logout" 
                                    method="post" 
                                    as="button"
                                    className="ml-4 px-3 py-2 text-sm font-medium text-red-600 hover:text-red-800"
                                >
                                    Logout
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
            <main className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {children}
                </div>
            </main>
        </div>
    );
}
