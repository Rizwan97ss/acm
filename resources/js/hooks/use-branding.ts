import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const HEX = /^#([0-9a-f]{6})$/i;

/**
 * WCAG relative luminance, used to pick readable text on the brand colour.
 */
const luminance = (hex: string): number => {
    const channels = [0, 2, 4].map((offset) => {
        const value = parseInt(hex.slice(1 + offset, 3 + offset), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
};

const TOKENS = ['--primary', '--ring', '--chart-1'];
const FOREGROUND_TOKENS = ['--primary-foreground'];

/**
 * Applies the school's brand colour to the design tokens so every primary
 * button, focus ring and chart picks it up without per-component changes.
 */
export function useBranding(): void {
    const { branding } = usePage().props;
    const color = branding?.primaryColor;

    useEffect(() => {
        const root = document.documentElement;

        if (!color || !HEX.test(color)) {
            return;
        }

        const foreground = luminance(color) > 0.45 ? '#0a0a0a' : '#ffffff';

        TOKENS.forEach((token) => root.style.setProperty(token, color));
        FOREGROUND_TOKENS.forEach((token) =>
            root.style.setProperty(token, foreground),
        );

        return () => {
            [...TOKENS, ...FOREGROUND_TOKENS].forEach((token) =>
                root.style.removeProperty(token),
            );
        };
    }, [color]);
}
