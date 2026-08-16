export function diffLines(before: string, after: string): { type: 'same' | 'add' | 'remove'; line: string }[] {
    const left = before.split('\n');
    const right = after.split('\n');
    const rows: { type: 'same' | 'add' | 'remove'; line: string }[] = [];
    const max = Math.max(left.length, right.length);

    for (let index = 0; index < max; index += 1) {
        const a = left[index];
        const b = right[index];

        if (a === b) {
            if (a !== undefined) {
                rows.push({ type: 'same', line: a });
            }

            continue;
        }

        if (a !== undefined) {
            rows.push({ type: 'remove', line: a });
        }

        if (b !== undefined) {
            rows.push({ type: 'add', line: b });
        }
    }

    return rows;
}
