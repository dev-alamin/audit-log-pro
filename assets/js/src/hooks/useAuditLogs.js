import apiFetch from "@wordpress/api-fetch";
import { useState, useEffect } from "@wordpress/element";

function useAuditLogs(filters) {
    const [logs, setLogs] = useState([]);
    const [meta, setMeta] = useState({ nextCursor: null, hasMore: false });
    const [status, setStatus] = useState('idle'); // idle | loading | success | error
    const [error, setError] = useState(null);

    useEffect(() => {
        let isMounted = true;
        setStatus('loading');

        const query = new URLSearchParams(filters).toString();

        apiFetch({ path: `/adtlogpro/v1/logs?${query}` })
            .then((result) => {
                if (!isMounted) return;
                setLogs(result.data);
                setMeta({ nextCursor: result.next_cursor, hasMore: result.has_more });
                setStatus('success');
            })
            .catch((err) => {
                if (!isMounted) return;
                setError(err);
                setStatus('error');
            });

        return () => { isMounted = false; };
    }, [JSON.stringify(filters)]);  // re-fetch whenever filters actually change

    return { logs, meta, status, error };
}

export default useAuditLogs;