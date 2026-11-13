import { useId, useState } from 'react';
import { cn } from '@/lib/utils';

export type BarDatum = {
    label: string;
    value: number;
    /** Extra tooltip line, e.g. "3 failed attempts". */
    detail?: string;
};

type BarChartProps = {
    data: BarDatum[];
    title: string;
    valueLabel: string;
    /** Show every nth axis label to avoid collisions on narrow screens. */
    labelEvery?: number;
};

/**
 * Single-series vertical bar chart built from plain elements: brand-coloured
 * bars with 4px rounded data-ends on a recessive baseline, 2px gaps, a
 * hover/focus tooltip per bar, and an equivalent table for screen readers.
 */
export default function BarChart({
    data,
    title,
    valueLabel,
    labelEvery = 1,
}: BarChartProps) {
    const id = useId();
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(1, ...data.map((datum) => datum.value));

    return (
        <figure className="relative" aria-labelledby={`${id}-caption`}>
            <figcaption id={`${id}-caption`} className="sr-only">
                {title}
            </figcaption>
            <div
                className="flex h-44 items-end gap-0.5 border-b border-border"
                aria-hidden
            >
                {data.map((datum, index) => (
                    <div
                        key={datum.label}
                        className="flex h-full flex-1 cursor-default items-end"
                        tabIndex={0}
                        onMouseEnter={() => setActive(index)}
                        onMouseLeave={() => setActive(null)}
                        onFocus={() => setActive(index)}
                        onBlur={() => setActive(null)}
                    >
                        <div
                            className={cn(
                                'w-full rounded-t-[4px] bg-primary transition-opacity',
                                active !== null &&
                                    active !== index &&
                                    'opacity-40',
                            )}
                            style={{
                                height:
                                    datum.value > 0
                                        ? `${Math.max(2, (datum.value / max) * 100)}%`
                                        : 0,
                            }}
                        />
                    </div>
                ))}
            </div>
            <div
                className="mt-1.5 flex gap-0.5 text-[10px] text-muted-foreground"
                aria-hidden
            >
                {data.map((datum, index) => (
                    <span
                        key={datum.label}
                        className="flex-1 truncate text-center"
                    >
                        {index % labelEvery === 0 ? datum.label : ''}
                    </span>
                ))}
            </div>
            {active !== null && data[active] && (
                <div
                    className="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                    style={{ left: `${((active + 0.5) / data.length) * 100}%` }}
                    aria-hidden
                >
                    <p className="font-medium">{data[active].label}</p>
                    <p className="tabular-nums">
                        {data[active].value.toLocaleString()} {valueLabel}
                    </p>
                    {data[active].detail && (
                        <p className="text-muted-foreground">
                            {data[active].detail}
                        </p>
                    )}
                </div>
            )}
            <table className="sr-only">
                <caption>{title}</caption>
                <tbody>
                    {data.map((datum) => (
                        <tr key={datum.label}>
                            <th scope="row">{datum.label}</th>
                            <td>
                                {datum.value} {valueLabel}
                                {datum.detail ? `, ${datum.detail}` : ''}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
