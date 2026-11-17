import type { CSSProperties, ElementType, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * True once the element has scrolled into view (and stays true). Without
 * IntersectionObserver the element counts as visible straight away.
 */
export function useInView<T extends Element>(
    rootMargin = '0px 0px -10% 0px',
): [React.RefObject<T | null>, boolean] {
    const ref = useRef<T>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (!element || typeof IntersectionObserver === 'undefined') {
            setVisible(true);

            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { rootMargin },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [rootMargin]);

    return [ref, visible];
}

/**
 * Fades and lifts its content in when scrolled into view. Content is only hidden
 * after hydration (the `landing-motion` class on <html>), so it is always readable
 * without JavaScript, and `prefers-reduced-motion` disables the effect in CSS.
 */
export function Reveal({
    as: Tag = 'div',
    delay = 0,
    className,
    children,
}: {
    as?: ElementType;
    delay?: number;
    className?: string;
    children: ReactNode;
}) {
    const [ref, visible] = useInView<HTMLElement>();

    return (
        <Tag
            ref={ref}
            data-reveal=""
            data-visible={visible ? '' : undefined}
            style={{ '--reveal-delay': `${delay}ms` } as CSSProperties}
            className={className}
        >
            {children}
        </Tag>
    );
}

/**
 * Marks the document as ready for reveal animations once React is running.
 */
export function useLandingMotion(): void {
    useEffect(() => {
        document.documentElement.classList.add('landing-motion');

        return () =>
            document.documentElement.classList.remove('landing-motion');
    }, []);
}

/**
 * Counts up to `value` when scrolled into view; shows the final value straight away
 * for people who prefer reduced motion.
 */
export function CountUp({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    const [ref, visible] = useInView<HTMLSpanElement>();
    const [shown, setShown] = useState(value);

    useEffect(() => {
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setShown(0);
        }
    }, []);

    useEffect(() => {
        if (!visible) {
            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setShown(value);

            return;
        }

        const duration = 1200;
        const start = performance.now();
        let frame = 0;

        const tick = (now: number): void => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);

            setShown(Math.round(value * eased));

            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            }
        };

        setShown(0);
        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [visible, value]);

    return (
        <span ref={ref} className={cn('tabular-nums', className)}>
            {shown.toLocaleString()}
        </span>
    );
}
