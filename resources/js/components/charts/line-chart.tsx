import { useId, useState } from 'react';
import { cn } from '@/lib/utils';

export type LineSeries = {
    name: string;
    /** One value per label; null leaves a gap. */
    values: (number | null)[];
    /** Tailwind text colour class; the stroke uses currentColor. */
    colorClass: string;
    variant?: 'line' | 'dashed' | 'dots';
    /** Optional uncertainty band drawn behind the line. */
    band?: { low: (number | null)[]; high: (number | null)[] };
};

type LineChartProps = {
    title: string;
    labels: string[];
    series: LineSeries[];
    format: (value: number) => string;
    /** Fixed y-range, e.g. [0, 100] for percentages. */
    domain?: [number, number];
    labelEvery?: number;
    height?: number;
};

const WIDTH = 640;
const PAD = { top: 12, right: 12, bottom: 4, left: 4 };

/**
 * Multi-series SVG line chart: 2px lines, ≥8px dots, a recessive baseline and
 * gridlines, a hover/focus crosshair with tooltip, a legend for two or more series
 * and an equivalent table for screen readers. One y-axis only.
 */
export default function LineChart({
    title,
    labels,
    series,
    format,
    domain,
    labelEvery = 1,
    height = 200,
}: LineChartProps) {
    const id = useId();
    const [active, setActive] = useState<number | null>(null);

    const all = series.flatMap((s) => [
        ...s.values,
        ...(s.band?.low ?? []),
        ...(s.band?.high ?? []),
    ]);
    const numbers = all.filter((v): v is number => v !== null);
    const min = domain?.[0] ?? Math.min(0, ...numbers);
    const max = domain?.[1] ?? Math.max(1, ...numbers) * 1.08;
    const innerW = WIDTH - PAD.left - PAD.right;
    const innerH = height - PAD.top - PAD.bottom;
    const x = (i: number): number =>
        PAD.left +
        (labels.length <= 1 ? innerW / 2 : (i / (labels.length - 1)) * innerW);
    const y = (v: number): number =>
        PAD.top + innerH - ((v - min) / (max - min || 1)) * innerH;

    const path = (values: (number | null)[]): string =>
        values
            .map((v, i) =>
                v === null
                    ? ''
                    : `${i === 0 || values[i - 1] === null ? 'M' : 'L'}${x(i).toFixed(1)},${y(v).toFixed(1)}`,
            )
            .join(' ');

    const bandPath = (
        low: (number | null)[],
        high: (number | null)[],
    ): string => {
        const idx = labels
            .map((_, i) => i)
            .filter((i) => low[i] != null && high[i] != null);

        if (idx.length === 0) {
            return '';
        }

        const top = idx.map((i) => `${x(i)},${y(high[i] as number)}`);
        const bottom = [...idx]
            .reverse()
            .map((i) => `${x(i)},${y(low[i] as number)}`);

        return `M${top.join(' L')} L${bottom.join(' L')} Z`;
    };

    const gridValues = [0.25, 0.5, 0.75, 1].map((f) => min + (max - min) * f);

    return (
        <figure className="relative" aria-labelledby={`${id}-caption`}>
            <figcaption id={`${id}-caption`} className="sr-only">
                {title}
            </figcaption>
            {series.length > 1 && (
                <div
                    className="mb-3 flex flex-wrap gap-4 text-xs text-muted-foreground"
                    aria-hidden
                >
                    {series.map((s) => (
                        <span
                            key={s.name}
                            className="flex items-center gap-1.5"
                        >
                            <svg width="16" height="8" className={s.colorClass}>
                                <line
                                    x1="0"
                                    y1="4"
                                    x2="16"
                                    y2="4"
                                    stroke="currentColor"
                                    strokeWidth={s.variant === 'dots' ? 0 : 2}
                                    strokeDasharray={
                                        s.variant === 'dashed'
                                            ? '4 3'
                                            : undefined
                                    }
                                />
                                {s.variant === 'dots' && (
                                    <circle
                                        cx="8"
                                        cy="4"
                                        r="3"
                                        fill="currentColor"
                                    />
                                )}
                            </svg>
                            {s.name}
                        </span>
                    ))}
                </div>
            )}
            <svg
                viewBox={`0 0 ${WIDTH} ${height}`}
                className="h-auto w-full overflow-visible"
                aria-hidden
                onMouseLeave={() => setActive(null)}
            >
                {gridValues.map((v) => (
                    <line
                        key={v}
                        x1={PAD.left}
                        x2={WIDTH - PAD.right}
                        y1={y(v)}
                        y2={y(v)}
                        className="stroke-border"
                        strokeDasharray="2 4"
                    />
                ))}
                <line
                    x1={PAD.left}
                    x2={WIDTH - PAD.right}
                    y1={y(min)}
                    y2={y(min)}
                    className="stroke-border"
                />
                {series.map(
                    (s) =>
                        s.band && (
                            <path
                                key={`${s.name}-band`}
                                d={bandPath(s.band.low, s.band.high)}
                                className={s.colorClass}
                                fill="currentColor"
                                fillOpacity={0.12}
                            />
                        ),
                )}
                {series.map((s) =>
                    s.variant === 'dots' ? (
                        <g key={s.name} className={s.colorClass}>
                            {s.values.map(
                                (v, i) =>
                                    v !== null && (
                                        <circle
                                            key={i}
                                            cx={x(i)}
                                            cy={y(v)}
                                            r={labels.length > 60 ? 2.5 : 4}
                                            fill="currentColor"
                                            fillOpacity={0.55}
                                        />
                                    ),
                            )}
                        </g>
                    ) : (
                        <path
                            key={s.name}
                            d={path(s.values)}
                            className={s.colorClass}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={2}
                            strokeLinejoin="round"
                            strokeLinecap="round"
                            strokeDasharray={
                                s.variant === 'dashed' ? '6 4' : undefined
                            }
                        />
                    ),
                )}
                {active !== null && (
                    <g>
                        <line
                            x1={x(active)}
                            x2={x(active)}
                            y1={PAD.top}
                            y2={y(min)}
                            className="stroke-muted-foreground"
                            strokeWidth={1}
                        />
                        {series.map((s) => {
                            const v = s.values[active];

                            return (
                                v !== null &&
                                v !== undefined && (
                                    <circle
                                        key={s.name}
                                        cx={x(active)}
                                        cy={y(v)}
                                        r={4.5}
                                        className={cn(
                                            s.colorClass,
                                            'stroke-card',
                                        )}
                                        fill="currentColor"
                                        strokeWidth={2}
                                    />
                                )
                            );
                        })}
                    </g>
                )}
                {labels.map((label, i) => {
                    const step = innerW / Math.max(1, labels.length - 1);

                    return (
                        <rect
                            key={label}
                            x={x(i) - step / 2}
                            y={0}
                            width={Math.max(step, 4)}
                            height={height}
                            fill="transparent"
                            onMouseEnter={() => setActive(i)}
                        />
                    );
                })}
            </svg>
            <div
                className="mt-1.5 flex justify-between text-[10px] text-muted-foreground"
                aria-hidden
            >
                {labels.map((label, i) =>
                    i % labelEvery === 0 || i === labels.length - 1 ? (
                        <span key={label}>{label}</span>
                    ) : null,
                )}
            </div>
            {active !== null && labels[active] && (
                <div
                    className="pointer-events-none absolute top-6 z-10 -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                    style={{
                        left: `${Math.min(88, Math.max(12, (x(active) / WIDTH) * 100))}%`,
                    }}
                    aria-hidden
                >
                    <p className="font-medium">{labels[active]}</p>
                    {series.map((s) => {
                        const v = s.values[active];

                        return v === null || v === undefined ? null : (
                            <p key={s.name} className="tabular-nums">
                                {s.name}: {format(v)}
                                {s.band &&
                                    s.band.low[active] != null &&
                                    s.band.high[active] != null &&
                                    ` (${format(s.band.low[active] as number)} – ${format(s.band.high[active] as number)})`}
                            </p>
                        );
                    })}
                </div>
            )}
            <table className="sr-only">
                <caption>{title}</caption>
                <thead>
                    <tr>
                        <th scope="col">Period</th>
                        {series.map((s) => (
                            <th key={s.name} scope="col">
                                {s.name}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {labels.map((label, i) => (
                        <tr key={label}>
                            <th scope="row">{label}</th>
                            {series.map((s) => (
                                <td key={s.name}>
                                    {s.values[i] == null
                                        ? '—'
                                        : format(s.values[i] as number)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
