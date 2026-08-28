/** @jest-environment node */

import {
    analyzeMarkdown,
    formatDiagnostic,
    slugifyHeading,
    validateMarkdownSet,
} from '../../../scripts/docs/check-markdown-integrity.js';

describe('Markdown integrity checker', () => {
    test('accepts balanced front matter, fences, and explicit heading IDs', () => {
        const analysis = analyzeMarkdown('docs/example.md', [
            '---',
            'title: Example',
            '---',
            '',
            '# Overview',
            '',
            '## Configured heading {#configured}',
            '',
            '```js',
            'const value = true;',
            '```',
        ].join('\n'));

        expect(analysis.diagnostics).toEqual([]);
        expect(analysis.anchors).toEqual(new Set(['overview', 'configured']));
    });

    test('reports unclosed front matter and fenced code blocks at their opening line', () => {
        const frontMatter = analyzeMarkdown('docs/front.md', '---\ntitle: Broken');
        const fence = analyzeMarkdown('docs/fence.md', '# Example\n\n~~~~js\nconst value = true;');

        expect(frontMatter.diagnostics).toEqual([
            expect.objectContaining({ file: 'docs/front.md', line: 1, rule: 'front-matter' }),
        ]);
        expect(fence.diagnostics).toEqual([
            expect.objectContaining({ file: 'docs/fence.md', line: 3, rule: 'fence' }),
        ]);
    });

    test('resolves Markdown, generated HTML, same-page, and duplicate-heading fragments', () => {
        const files = new Map([
            ['docs/index.md', [
                '# Home',
                '[Same page](#home)',
                '[Guide](guide.md#install)',
                '[Generated page](guide.html#install-1)',
            ].join('\n')],
            ['docs/guide.md', '# Install\n\n## Install'],
        ]);

        expect(validateMarkdownSet(files, new Set(files.keys()))).toEqual([]);
    });

    test('reports missing local files and heading fragments with source positions', () => {
        const files = new Map([
            ['docs/index.md', '# Home\n\n[Missing](missing.md)\n[Bad anchor](guide.md#unknown)'],
            ['docs/guide.md', '# Install'],
        ]);
        const diagnostics = validateMarkdownSet(files, new Set(files.keys()));

        expect(diagnostics).toEqual([
            expect.objectContaining({ file: 'docs/index.md', line: 3, rule: 'link-target' }),
            expect.objectContaining({ file: 'docs/index.md', line: 4, rule: 'heading-fragment' }),
        ]);
        expect(formatDiagnostic(diagnostics[0])).toMatch(/^docs\/index\.md:3:\d+: \[link-target\]/);
    });

    test('accepts absent generated API artifacts beneath the exact generated roots', () => {
        const files = new Map([
            ['docs/api/index.md', [
                '# API reference',
                '[PHP](php/index.html)',
                '[JavaScript](js/Controller.html#details)',
            ].join(String.fromCharCode(10))],
        ]);

        expect(validateMarkdownSet(files, new Set(files.keys()))).toEqual([]);
    });

    test('does not exempt paths adjacent to or escaping the generated API roots', () => {
        const files = new Map([
            ['docs/index.md', [
                '# Home',
                '[PHP sibling](api/phpish/index.html)',
                '[JavaScript sibling](api/javascript/index.html)',
                '[Escaped generated path](api/php/../manual.html)',
            ].join(String.fromCharCode(10))],
        ]);
        const diagnostics = validateMarkdownSet(files, new Set(files.keys()));

        expect(diagnostics).toHaveLength(3);
        expect(diagnostics).toEqual(expect.arrayContaining([
            expect.objectContaining({ line: 2, rule: 'link-target' }),
            expect.objectContaining({ line: 3, rule: 'link-target' }),
            expect.objectContaining({ line: 4, rule: 'link-target' }),
        ]));
    });

    test('detects literal Liquid delimiters in code while allowing raw blocks and active site output', () => {
        const analysis = analyzeMarkdown('docs/liquid.md', [
            '# Liquid',
            '',
            '`{{variable}}`',
            '',
            '```yaml',
            'value: ${{ secrets.VALUE }}',
            '```',
            '',
            '{% raw %}',
            '`{{protected}}`',
            '{% endraw %}',
            '',
            '{{ site.title }}',
        ].join('\n'));

        expect(analysis.diagnostics).toEqual([
            expect.objectContaining({ line: 3, rule: 'liquid-literal' }),
            expect.objectContaining({ line: 6, rule: 'liquid-literal' }),
        ]);
    });

    test('limits Liquid diagnostics to handwritten published documentation', () => {
        expect(analyzeMarkdown('.github/skills/example/SKILL.md', '{{name}}').diagnostics).toEqual([]);
        expect(analyzeMarkdown('docs/api/index.md', '{{name}}').diagnostics).toEqual([
            expect.objectContaining({ rule: 'liquid-literal' }),
        ]);
        expect(analyzeMarkdown('docs/api/js/generated.md', '{{name}}').diagnostics).toEqual([]);
        expect(analyzeMarkdown('docs/api/php/generated.md', '{{name}}').diagnostics).toEqual([]);
    });

    test('reports an unclosed Liquid raw block', () => {
        const analysis = analyzeMarkdown('docs/raw.md', '{% raw %}\n`{{safe but unclosed}}`');

        expect(analysis.diagnostics).toEqual([
            expect.objectContaining({ file: 'docs/raw.md', line: 1, rule: 'liquid-raw' }),
        ]);
    });

    test('generates stable Unicode-aware heading slugs', () => {
        expect(slugifyHeading('API & Tenant `Cache`')).toBe('api-tenant-cache');
        expect(slugifyHeading('Crème brûlée')).toBe('crème-brûlée');
    });
});
