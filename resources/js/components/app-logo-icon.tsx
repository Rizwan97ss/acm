import type { SVGAttributes } from 'react';

/**
 * Mortarboard mark used when the institute has not uploaded its own logo.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden
        >
            <path d="M12 3 1 8.5 12 14l9-4.5V16h2V8.5L12 3Z" />
            <path d="M5 12.2v4.3C5 18.4 8.1 20 12 20s7-1.6 7-3.5v-4.3l-7 3.5-7-3.5Z" />
        </svg>
    );
}
