const LOG_LEVEL_PATTERN = /\[(ERROR|SUCCESS|INFO|WARN(?:ING)?|DEBUG)\]/i;

function escapeHtml(text: string): string {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function highlightLogLine(line: string): string {
    const escaped = escapeHtml(line);

    return escaped
        .replace(/(\[ERROR\])/gi, '<span class="docs-log-level docs-log-level-error">$1</span>')
        .replace(/(\[SUCCESS\])/gi, '<span class="docs-log-level docs-log-level-success">$1</span>')
        .replace(/(\[INFO\])/gi, '<span class="docs-log-level docs-log-level-info">$1</span>')
        .replace(/(\[WARN(?:ING)?\])/gi, '<span class="docs-log-level docs-log-level-warn">$1</span>')
        .replace(/(\[DEBUG\])/gi, '<span class="docs-log-level docs-log-level-debug">$1</span>');
}

export function enhanceDocsContent(root: HTMLElement, options: { steps?: boolean } = {}): void {
    root.querySelectorAll<HTMLButtonElement>('.docs-code-copy').forEach((button) => {
        button.onclick = async () => {
            const code = button.parentElement?.querySelector('code')?.textContent ?? '';
            await navigator.clipboard.writeText(code);
            button.textContent = 'Copied';
            window.setTimeout(() => {
                button.textContent = 'Copy';
            }, 1500);
        };
    });

    root.querySelectorAll<HTMLElement>('.docs-code-block .docs-code').forEach((code) => {
        if (code.dataset.enhanced === 'true') {
            return;
        }

        const text = code.textContent ?? '';
        const block = code.closest('.docs-code-block');

        if (LOG_LEVEL_PATTERN.test(text)) {
            block?.classList.add('docs-terminal-block');
            code.classList.add('docs-log-output');
            code.innerHTML = text
                .split('\n')
                .map((line) => `<span class="docs-log-line">${highlightLogLine(line) || '&nbsp;'}</span>`)
                .join('');
        }

        code.dataset.enhanced = 'true';
    });

    if (options.steps) {
        root.querySelectorAll<HTMLOListElement>('ol').forEach((list) => {
            if (list.closest('ol ol') || list.classList.contains('docs-steps-list')) {
                return;
            }

            list.classList.add('docs-steps-list');
        });
    }
}
