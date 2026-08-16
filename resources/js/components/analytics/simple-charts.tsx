type Point = { date: string; views: number };

export function SimpleLineChart({ data, label }: { data: Point[]; label: string }) {
    if (data.length === 0) {
        return <p className="text-sm text-muted-foreground">No data yet.</p>;
    }

    const max = Math.max(...data.map((row) => row.views), 1);
    const width = 640;
    const height = 160;
    const padding = 24;
    const innerWidth = width - padding * 2;
    const innerHeight = height - padding * 2;
    const step = data.length > 1 ? innerWidth / (data.length - 1) : 0;

    const points = data
        .map((row, index) => {
            const x = padding + index * step;
            const y = padding + innerHeight - (row.views / max) * innerHeight;

            return `${x},${y}`;
        })
        .join(' ');

    return (
        <div className="space-y-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <svg viewBox={`0 0 ${width} ${height}`} className="h-40 w-full" role="img" aria-label={label}>
                <polyline
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    className="text-primary"
                    points={points}
                />
                {data.map((row, index) => {
                    const x = padding + index * step;
                    const y = padding + innerHeight - (row.views / max) * innerHeight;

                    return <circle key={row.date} cx={x} cy={y} r="3" className="fill-primary" />;
                })}
            </svg>
        </div>
    );
}

export function SimpleBarChart({
    data,
    label,
}: {
    data: { title: string; views: number }[];
    label: string;
}) {
    if (data.length === 0) {
        return <p className="text-sm text-muted-foreground">No data yet.</p>;
    }

    const max = Math.max(...data.map((row) => row.views), 1);

    return (
        <div className="space-y-3">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <div className="space-y-2">
                {data.map((row) => (
                    <div key={row.title} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 text-sm">
                        <div className="space-y-1">
                            <p className="truncate font-medium">{row.title}</p>
                            <div className="h-2 rounded-full bg-muted">
                                <div
                                    className="h-2 rounded-full bg-primary"
                                    style={{ width: `${Math.max((row.views / max) * 100, 4)}%` }}
                                />
                            </div>
                        </div>
                        <span className="text-muted-foreground">{row.views}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
