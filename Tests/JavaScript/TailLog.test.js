import { beforeEach, describe, expect, it } from 'vitest';
import appJsSource from '../../Resources/public/assets/app.js?raw';

// app.js is a classic script without exports, so evaluate it and pull out tailLog.
function loadTailLog() {
    return new Function(`${appJsSource}\nreturn tailLog;`)();
}

const encoder = new TextEncoder();

function mockResponse(chunks) {
    let index = 0;

    return {
        body: {
            getReader: () => ({
                read: async () => index < chunks.length
                    ? { value: chunks[index++], done: false }
                    : { value: undefined, done: true },
            }),
        },
    };
}

function composerChunks(lineCount, linesPerChunk, statusLine) {
    const chunks = [];

    for (let i = 0; i < lineCount; i += linesPerChunk) {
        let text = '';
        for (let j = i; j < Math.min(i + linesPerChunk, lineCount); j++) {
            text += `  - Upgrading shopware/package-${j} (6.7.0.0 => 6.7.1.0): Extracting archive\n`;
        }
        chunks.push(encoder.encode(text));
    }

    if (statusLine) {
        chunks.push(encoder.encode(statusLine));
    }

    return chunks;
}

describe('tailLog', () => {
    let tailLog;
    let element;

    beforeEach(() => {
        tailLog = loadTailLog();
        element = document.createElement('pre');
        document.body.appendChild(element);
    });

    it('renders log lines and resolves with the status on success', async () => {
        const response = mockResponse(composerChunks(50, 10, '{"success":true}'));

        const result = await tailLog(response, element);

        expect(result).toEqual({ success: true });
        const lines = element.textContent.trim().split('\n');
        expect(lines).toHaveLength(50);
        expect(lines[0]).toContain('shopware/package-0');
        expect(lines[49]).toContain('shopware/package-49');
    });

    it('throws "update failed" on a success:false status line', async () => {
        const response = mockResponse(composerChunks(5, 5, '{"success":false}'));

        await expect(tailLog(response, element)).rejects.toThrow('update failed');
        expect(element.textContent.trim().split('\n')).toHaveLength(5);
    });

    it('throws "Unexpected end of stream" when the stream ends without a status line', async () => {
        const response = mockResponse(composerChunks(3, 3, null));

        await expect(tailLog(response, element)).rejects.toThrow('Unexpected end of stream');
    });

    it('handles a status line preceded by log output in the same chunk', async () => {
        const response = mockResponse([encoder.encode('last log line\n{"success":true}')]);

        const result = await tailLog(response, element);

        expect(result).toEqual({ success: true });
        expect(element.textContent).toContain('last log line');
    });

    it('decodes multi-byte UTF-8 characters split across chunks', async () => {
        const bytes = encoder.encode('Größe geändert\n');
        const response = mockResponse([
            bytes.slice(0, 3), // ends mid-'ö' (0xC3 0xB6 at byte offsets 2-3)
            bytes.slice(3),
            encoder.encode('{"success":true}'),
        ]);

        await tailLog(response, element);

        expect(element.textContent).toContain('Größe geändert');
    });

    it('does not treat JSON-like log lines as the status line', async () => {
        const response = mockResponse([
            encoder.encode('123\n"quoted"\nnull\n{"other":true}\n'),
            encoder.encode('{"success":true}'),
        ]);

        const result = await tailLog(response, element);

        expect(result).toEqual({ success: true });
        expect(element.textContent.trim().split('\n')).toHaveLength(4);
    });

    it('renders log output as text, not as HTML', async () => {
        const response = mockResponse([
            encoder.encode('<img src=x onerror=alert(1)>\n'),
            encoder.encode('{"success":true}'),
        ]);

        await tailLog(response, element);

        expect(element.querySelector('img')).toBeNull();
        expect(element.textContent).toContain('<img src=x onerror=alert(1)>');
    });

    it('skips empty lines', async () => {
        const response = mockResponse([
            encoder.encode('first\n\n\nsecond\n'),
            encoder.encode('{"success":true}'),
        ]);

        await tailLog(response, element);

        expect(element.textContent.trim().split('\n')).toHaveLength(2);
    });

    it('updates the DOM per chunk, not per line', async () => {
        // Guards against the quadratic per-line rendering that froze the
        // browser on long composer outputs (shopware/shopware#17236).
        // Every render op ends with a scrollTop assignment, so count those.
        let scrollUpdates = 0;
        Object.defineProperty(element, 'scrollTop', {
            get: () => 0,
            set: () => {
                scrollUpdates++;
            },
        });

        const response = mockResponse(composerChunks(100, 10, '{"success":true}'));

        await tailLog(response, element);

        expect(element.textContent.trim().split('\n')).toHaveLength(100);
        expect(scrollUpdates).toBeLessThanOrEqual(10);
    });
});
