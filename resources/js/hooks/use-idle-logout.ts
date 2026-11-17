import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { heartbeat } from '@/routes/session';

/** Shared between tabs so activity in one keeps the others signed in. */
const STORAGE_KEY = 'idle-logout:last-contact';

/** How long before sign-out the warning appears. */
export const IDLE_WARNING_SECONDS = 60;

/** Activity sends a keep-alive at most this often. */
const HEARTBEAT_INTERVAL_MS = 30_000;

/** Small margin so the server agrees the session has expired. */
const EXPIRY_GRACE_MS = 2_000;

const ACTIVITY_EVENTS = [
    'pointerdown',
    'keydown',
    'wheel',
    'touchstart',
    'mousemove',
    'scroll',
] as const;

function readShared(): number {
    try {
        return Number(window.localStorage.getItem(STORAGE_KEY)) || 0;
    } catch {
        return 0;
    }
}

function writeShared(timestamp: number): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, String(timestamp));
    } catch {
        // Private mode or blocked storage: this tab still keeps its own clock.
    }
}

/**
 * Signs the user out after the school's idle timeout.
 *
 * The clock measures time since the server last heard from this browser (a page visit or
 * a keep-alive), so it matches the server's own idle check exactly. Activity on a page
 * sends a keep-alive at most every 30 seconds. A minute before expiry it returns the
 * seconds left so a warning can be shown. On expiry it reloads the page; the server signs
 * the session out and shows the login page with an explanation.
 *
 * @returns seconds until sign-out while the warning should show, otherwise null
 */
export function useIdleLogout(): {
    secondsLeft: number | null;
    staySignedIn: () => void;
} {
    const { auth } = usePage().props;
    const timeoutMs = auth.user ? auth.idleTimeout * 60_000 : 0;
    const lastContact = useRef(Date.now());
    const expired = useRef(false);
    const inFlight = useRef(false);
    const [secondsLeft, setSecondsLeft] = useState<number | null>(null);

    const markContact = useCallback((timestamp = Date.now()): void => {
        lastContact.current = timestamp;
        writeShared(timestamp);
    }, []);

    const expire = useCallback((): void => {
        if (expired.current) {
            return;
        }

        expired.current = true;
        router.reload();
    }, []);

    const sendHeartbeat = useCallback((): void => {
        if (inFlight.current || expired.current) {
            return;
        }

        inFlight.current = true;

        fetch(heartbeat.url(), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (response.status === 204) {
                    markContact();
                    setSecondsLeft(null);
                } else {
                    expire();
                }
            })
            .catch(() => {
                // Offline for a moment: the next activity tries again.
            })
            .finally(() => {
                inFlight.current = false;
            });
    }, [expire, markContact]);

    useEffect(() => {
        if (timeoutMs <= 0) {
            return;
        }

        expired.current = false;
        markContact();

        const offFinish = router.on('finish', () => markContact());

        const onActivity = (): void => {
            const last = Math.max(lastContact.current, readShared());

            if (Date.now() - last >= HEARTBEAT_INTERVAL_MS) {
                sendHeartbeat();
            }
        };

        const onStorage = (event: StorageEvent): void => {
            if (event.key === STORAGE_KEY && event.newValue) {
                lastContact.current = Math.max(
                    lastContact.current,
                    Number(event.newValue) || 0,
                );
            }
        };

        const tick = window.setInterval(() => {
            const last = Math.max(lastContact.current, readShared());
            const remainingMs = timeoutMs - (Date.now() - last);

            if (remainingMs <= -EXPIRY_GRACE_MS) {
                expire();

                return;
            }

            setSecondsLeft(
                remainingMs <= IDLE_WARNING_SECONDS * 1000
                    ? Math.max(0, Math.ceil(remainingMs / 1000))
                    : null,
            );
        }, 1000);

        ACTIVITY_EVENTS.forEach((name) =>
            window.addEventListener(name, onActivity, { passive: true }),
        );
        window.addEventListener('storage', onStorage);

        return () => {
            offFinish();
            window.clearInterval(tick);
            ACTIVITY_EVENTS.forEach((name) =>
                window.removeEventListener(name, onActivity),
            );
            window.removeEventListener('storage', onStorage);
        };
    }, [timeoutMs, expire, markContact, sendHeartbeat]);

    return { secondsLeft, staySignedIn: sendHeartbeat };
}
