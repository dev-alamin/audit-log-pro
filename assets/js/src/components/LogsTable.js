import { createRoot, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

apiFetch.use( apiFetch.createNonceMiddleware( adtLogPro.nonce ) );
apiFetch.use( apiFetch.createRootURLMiddleware( adtLogPro.root ) );

const LogsTable = () => {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let isMounted = true;

        apiFetch({ path: '/adtlogpro/v1/logs?per_page=10' })
            .then((result) => {
                if (isMounted) {
                    setLogs(result);
                    setLoading(false);
                }
            })
            .catch((error) => {
                if (isMounted) {
                    console.error('Failed to fetch audit logs:', error);
                    setLoading(false);
                }
            });

        return () => {
            isMounted = false;
        };
    }, []);

    const badgeStyles = {
        user_login: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        user_logout: 'bg-slate-50 text-slate-600 ring-slate-500/20',
        user_registered: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        user_profile_updated: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        user_role_changed: 'bg-violet-50 text-violet-700 ring-violet-600/20',
        user_deleted: 'bg-red-50 text-red-700 ring-red-600/20',
        user_password_reset: 'bg-orange-50 text-orange-700 ring-orange-600/20',
    };

    if (loading) {
        return <div className="p-8 text-sm text-slate-500">Loading activity…</div>;
    }

    return (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
                <h1 className='text-center font-medium'>Audit Log Pro</h1>
                <table className="w-full text-sm text-left">
                    <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr>
                            <th className="px-5 py-3 font-medium">Event</th>
                            <th className="px-5 py-3 font-medium">Actor</th>
                            <th className="px-5 py-3 font-medium">Message</th>
                            <th className="px-5 py-3 font-medium">When</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {logs.map((log) => (
                            <tr key={log.id} className="hover:bg-slate-50/70 transition-colors">
                                <td className="px-5 py-3">
                                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${badgeStyles[log.event_type] || 'bg-slate-50 text-slate-600 ring-slate-500/20'}`}>
                                        {log.event_type.replace(/_/g, ' ')}
                                    </span>
                                </td>
                                <td className="px-5 py-3 text-slate-500 font-mono text-xs">#{log.user_id}</td>
                                <td className="px-5 py-3 text-slate-700">{log.message}</td>
                                <td className="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">{log.created_at}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default LogsTable
