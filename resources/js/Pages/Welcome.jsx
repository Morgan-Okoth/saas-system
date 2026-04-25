<?jsx
import GuestLayout from '../Layouts/GuestLayout';
import { Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <GuestLayout title="Welcome">
            <div className="text-center">
                <h1 className="text-3xl font-bold text-gray-900 mb-4">
                    School Management System
                </h1>
                <p className="text-gray-600 mb-8">
                    A complete SaaS platform for managing your school
                </p>
                <div className="space-x-4">
                    <Link
                        href="/register"
                        className="inline-block px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Register Your School
                    </Link>
                    <Link
                        href="/login"
                        className="inline-block px-6 py-2 bg-white text-blue-600 font-semibold rounded-lg border border-blue-600 hover:bg-blue-50 transition-colors"
                    >
                        Sign In
                    </Link>
                </div>
                <div className="mt-8 pt-8 border-t border-gray-200">
                    <ul className="text-sm text-gray-500 space-y-2">
                        <li>✓ 14-day free trial - no credit card required</li>
                        <li>✓ Full-featured school management</li>
                        <li>✓ Student records & attendance tracking</li>
                        <li>✓ Secure multi-tenant architecture</li>
                    </ul>
                </div>
            </div>
        </GuestLayout>
    );
}
