import { useEffect, useRef } from 'react';

export function useManualDebounce(callback: () => void, delay: number, deps: any[]) {
    const handler = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (handler.current) {
            clearTimeout(handler.current);
        }

        handler.current = setTimeout(() => {
            callback();
        }, delay);

        return () => {
            if (handler.current) {
                clearTimeout(handler.current);
            }
        };
    }, deps); // eslint-disable-line react-hooks/exhaustive-deps
}
