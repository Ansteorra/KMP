#!/usr/bin/env node

import { spawnSync } from 'node:child_process';
import {
    existsSync,
    lstatSync,
    readFileSync,
    realpathSync,
} from 'node:fs';
import { dirname, extname, posix, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const GENERATED_API_PREFIXES = ['docs/api/js/', 'docs/api/php/'];
const MARKDOWN_EXTENSIONS = new Set(['.md', '.markdown']);
const ACTIVE_LIQUID_OUTPUT = /^(?:content\b|(?:site|page|layout|jekyll)\.)|\|\s*(?:absolute_url|default|escape|markdownify|relative_url)\b/;
const ACTIVE_LIQUID_TAGS = new Set([
    'assign',
    'capture',
    'case',
    'comment',
    'else',
    'elsif',
    'endcapture',
    'endcase',
    'endcomment',
    'endfor',
    'endif',
    'for',
    'if',
    'include',
    'link',
    'post_url',
    'unless',
]);

/**
 * Inspect Markdown syntax that can be validated without resolving links.
 *
 * @param {string} file Repository-relative file path.
 * @param {string} source Markdown source.
 * @returns {{anchors: Set<string>, diagnostics: Array<object>, links: Array<object>}}
 */
export function analyzeMarkdown(file, source) {
    const normalized = source.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n');
    const lines = normalized.split('\n');
    const structure = analyzeStructure(file, lines);
    const anchors = collectAnchors(lines, structure);
    const links = collectLinks(lines, structure);
    const diagnostics = [
        ...structure.diagnostics,
        ...collectLiquidDiagnostics(file, lines, structure),
    ];

    return { anchors, diagnostics, links };
}

/**
 * Validate a set of repository-relative Markdown files.
 *
 * @param {Map<string, string>} markdownFiles File contents keyed by repository path.
 * @param {Set<string>} repositoryPaths Existing tracked or untracked, non-ignored paths.
 * @returns {Array<object>} Diagnostics sorted by file and source position.
 */
export function validateMarkdownSet(markdownFiles, repositoryPaths = new Set(markdownFiles.keys())) {
    const analyses = new Map();
    const diagnostics = [];

    for (const [file, source] of [...markdownFiles.entries()].sort(([left], [right]) => left.localeCompare(right))) {
        const analysis = analyzeMarkdown(file, source);
        analyses.set(file, analysis);
        diagnostics.push(...analysis.diagnostics);
    }

    for (const [file, analysis] of analyses) {
        for (const link of analysis.links) {
            diagnostics.push(...validateLink(file, link, analyses, repositoryPaths));
        }
    }

    return diagnostics.sort(compareDiagnostics);
}

/**
 * Convert a Markdown heading to the fragment form emitted by GitHub/Kramdown
 * for headings without an explicit ID.
 *
 * @param {string} heading Heading text.
 * @returns {string} Fragment slug.
 */
export function slugifyHeading(heading) {
    return decodeHtmlEntities(stripInlineMarkdown(heading))
        .trim()
        .toLocaleLowerCase('en-US')
        .replace(/[^\p{Letter}\p{Number}\p{Mark}\p{Connector_Punctuation}\-\s]/gu, '')
        .replace(/\s+/g, '-');
}

/**
 * Run the repository check and return a result without mutating the tree.
 *
 * @param {string} repoRoot Repository root.
 * @returns {{diagnostics: Array<object>, markdownCount: number}}
 */
export function validateRepository(repoRoot) {
    const repositoryFiles = listRepositoryPaths(repoRoot);
    const repositoryPaths = new Set();
    const markdownFiles = new Map();
    const rootRealPath = realpathSync(repoRoot);

    for (const file of repositoryFiles) {
        const absolutePath = resolve(repoRoot, file);
        if (!existsSync(absolutePath)) {
            continue;
        }

        const stats = lstatSync(absolutePath);
        if (!stats.isFile() && !stats.isSymbolicLink()) {
            continue;
        }

        if (stats.isSymbolicLink() && !isWithinRoot(rootRealPath, realpathSync(absolutePath))) {
            continue;
        }

        repositoryPaths.add(file);
        if (!isMarkdown(file) || isGeneratedMarkdown(file)) {
            continue;
        }

        markdownFiles.set(file, readFileSync(absolutePath, 'utf8'));
    }

    return {
        diagnostics: validateMarkdownSet(markdownFiles, repositoryPaths),
        markdownCount: markdownFiles.size,
    };
}

/**
 * Format a diagnostic for terminals and GitHub Actions annotations.
 *
 * @param {{column: number, file: string, line: number, message: string, rule: string}} diagnostic Diagnostic.
 * @returns {string} Actionable file:line:column message.
 */
export function formatDiagnostic(diagnostic) {
    return `${diagnostic.file}:${diagnostic.line}:${diagnostic.column}: [${diagnostic.rule}] ${diagnostic.message}`;
}

function analyzeStructure(file, lines) {
    const codeLines = new Set();
    const diagnostics = [];
    const frontMatterLines = new Set();
    let firstContentLine = 0;

    const frontMatterDelimiter = lines[0] === '---' || lines[0] === '+++' ? lines[0] : null;
    if (frontMatterDelimiter) {
        frontMatterLines.add(0);
        let closingLine = -1;
        for (let index = 1; index < lines.length; index++) {
            frontMatterLines.add(index);
            if (lines[index] === frontMatterDelimiter || (frontMatterDelimiter === '---' && lines[index] === '...')) {
                closingLine = index;
                break;
            }
        }

        if (closingLine === -1) {
            diagnostics.push(createDiagnostic(file, 1, 1, 'front-matter', `front matter opened with "${frontMatterDelimiter}" but never closed`));
            firstContentLine = lines.length;
        } else {
            firstContentLine = closingLine + 1;
        }
    }

    let fence = null;
    for (let index = firstContentLine; index < lines.length; index++) {
        const line = lines[index];
        if (!fence) {
            const opening = line.match(/^ {0,3}(`{3,}|~{3,})(.*)$/);
            if (!opening) {
                continue;
            }
            fence = {
                character: opening[1][0],
                length: opening[1].length,
                line: index + 1,
            };
            codeLines.add(index);
            continue;
        }

        codeLines.add(index);
        const closing = line.match(/^ {0,3}(`{3,}|~{3,})\s*$/);
        if (closing && closing[1][0] === fence.character && closing[1].length >= fence.length) {
            fence = null;
        }
    }

    if (fence) {
        diagnostics.push(createDiagnostic(file, fence.line, 1, 'fence', `fenced code block opened with ${fence.character.repeat(fence.length)} but never closed`));
    }

    return { codeLines, diagnostics, frontMatterLines };
}

function collectAnchors(lines, structure) {
    const anchors = new Set();
    const slugCounts = new Map();

    for (let index = 0; index < lines.length; index++) {
        if (isIgnoredLine(index, structure)) {
            continue;
        }

        const line = lines[index];
        for (const match of line.matchAll(/\b(?:id|name)\s*=\s*["']([^"']+)["']/gi)) {
            anchors.add(match[1]);
        }

        const atx = line.match(/^ {0,3}#{1,6}[ \t]+(.+?)\s*$/);
        if (atx) {
            addHeadingAnchor(atx[1].replace(/[ \t]+#+[ \t]*$/, ''), anchors, slugCounts);
            continue;
        }

        if (index + 1 < lines.length
            && line.trim() !== ''
            && !isIgnoredLine(index + 1, structure)
            && /^ {0,3}(?:=+|-+)\s*$/.test(lines[index + 1])) {
            addHeadingAnchor(line.trim(), anchors, slugCounts);
        }
    }

    return anchors;
}

function addHeadingAnchor(rawHeading, anchors, slugCounts) {
    const explicit = rawHeading.match(/\s*\{(?::\s*)?#([A-Za-z][\w:.-]*)[^}]*\}\s*$/);
    if (explicit) {
        anchors.add(explicit[1]);
        return;
    }

    const baseSlug = slugifyHeading(rawHeading);
    if (!baseSlug) {
        return;
    }

    const duplicateCount = slugCounts.get(baseSlug) || 0;
    anchors.add(duplicateCount === 0 ? baseSlug : `${baseSlug}-${duplicateCount}`);
    slugCounts.set(baseSlug, duplicateCount + 1);
}

function collectLinks(lines, structure) {
    const links = [];

    for (let index = 0; index < lines.length; index++) {
        if (isIgnoredLine(index, structure)) {
            continue;
        }

        const maskedLine = maskInlineCode(lines[index]);
        const reference = maskedLine.match(/^ {0,3}\[[^\]]+\]:\s*(?:<([^>]+)>|(\S+))/);
        if (reference) {
            const raw = reference[1] || reference[2];
            links.push({
                column: maskedLine.indexOf(raw) + 1,
                line: index + 1,
                target: unescapeMarkdownTarget(raw),
            });
        }

        links.push(...extractInlineLinks(maskedLine, index + 1));
    }

    return links;
}

function extractInlineLinks(line, lineNumber) {
    const links = [];

    for (let index = 0; index < line.length - 1; index++) {
        if (line[index] !== ']' || line[index + 1] !== '(' || line.lastIndexOf('[', index) === -1) {
            continue;
        }

        let cursor = index + 2;
        while (cursor < line.length && /\s/.test(line[cursor])) {
            cursor++;
        }

        const targetColumn = cursor + 1;
        let target = '';
        if (line[cursor] === '<') {
            const closing = findUnescaped(line, '>', cursor + 1);
            if (closing === -1) {
                continue;
            }
            target = line.slice(cursor + 1, closing);
        } else {
            const start = cursor;
            let depth = 0;
            let escaped = false;
            for (; cursor < line.length; cursor++) {
                const character = line[cursor];
                if (escaped) {
                    escaped = false;
                    continue;
                }
                if (character === '\\') {
                    escaped = true;
                    continue;
                }
                if (character === '(') {
                    depth++;
                    continue;
                }
                if (character === ')' && depth > 0) {
                    depth--;
                    continue;
                }
                if ((character === ')' || /\s/.test(character)) && depth === 0) {
                    break;
                }
            }
            target = line.slice(start, cursor);
        }

        if (target !== '') {
            links.push({
                column: targetColumn,
                line: lineNumber,
                target: unescapeMarkdownTarget(target),
            });
        }
    }

    return links;
}

function validateLink(sourceFile, link, analyses, repositoryPaths) {
    const parsed = parseLocalTarget(link.target);
    if (!parsed) {
        return [];
    }

    const diagnostics = [];
    const resolved = resolveTargetPath(sourceFile, parsed.path, repositoryPaths);
    if (!resolved) {
        diagnostics.push(createDiagnostic(
            sourceFile,
            link.line,
            link.column,
            'link-target',
            `local link target "${link.target}" does not resolve to a repository file`,
        ));
        return diagnostics;
    }

    if (parsed.fragment && analyses.has(resolved)) {
        const fragment = decodeUrlComponent(parsed.fragment);
        if (fragment === null) {
            diagnostics.push(createDiagnostic(
                sourceFile,
                link.line,
                link.column,
                'heading-fragment',
                `link fragment in "${link.target}" is not valid URL encoding`,
            ));
        } else if (!analyses.get(resolved).anchors.has(fragment)) {
            diagnostics.push(createDiagnostic(
                sourceFile,
                link.line,
                link.column,
                'heading-fragment',
                `fragment "#${fragment}" does not match a heading or explicit ID in "${resolved}"`,
            ));
        }
    }

    return diagnostics;
}

function parseLocalTarget(rawTarget) {
    const target = rawTarget.trim();
    if (!target
        || target.startsWith('//')
        || /^[A-Za-z][A-Za-z\d+.-]*:/.test(target)
        || /[{}]/.test(target)) {
        return null;
    }

    const hashIndex = target.indexOf('#');
    const beforeFragment = hashIndex === -1 ? target : target.slice(0, hashIndex);
    const queryIndex = beforeFragment.indexOf('?');
    const encodedPath = queryIndex === -1 ? beforeFragment : beforeFragment.slice(0, queryIndex);
    const decodedPath = decodeUrlComponent(encodedPath);
    if (decodedPath === null) {
        return { fragment: null, path: encodedPath };
    }

    return {
        fragment: hashIndex === -1 ? null : target.slice(hashIndex + 1),
        path: decodedPath,
    };
}

function resolveTargetPath(sourceFile, targetPath, repositoryPaths) {
    const sourceDirectory = posix.dirname(sourceFile);
    const rooted = targetPath.startsWith('/');
    const rawPath = targetPath === '' ? sourceFile : rooted
        ? targetPath.slice(1)
        : posix.join(sourceDirectory, targetPath);
    const normalized = posix.normalize(rawPath).replace(/^\.\//, '');
    if (normalized === '..' || normalized.startsWith('../')) {
        return null;
    }
    if (isGeneratedApiPath(normalized)) {
        return normalized;
    }

    const candidates = [normalized];
    const extension = extname(normalized).toLowerCase();
    if (extension === '.html') {
        candidates.push(`${normalized.slice(0, -5)}.md`, `${normalized.slice(0, -5)}.markdown`);
    } else if (!extension) {
        candidates.push(
            `${normalized}.md`,
            `${normalized}.markdown`,
            posix.join(normalized, 'README.md'),
            posix.join(normalized, 'index.md'),
        );
    }

    for (const candidate of candidates) {
        if (repositoryPaths.has(candidate)) {
            return candidate;
        }
    }

    if ([...repositoryPaths].some(file => file.startsWith(`${normalized.replace(/\/$/, '')}/`))) {
        return normalized.replace(/\/$/, '');
    }

    return null;
}

function collectLiquidDiagnostics(file, lines, structure) {
    if (!isPublishedMarkdown(file)) {
        return [];
    }
    const diagnostics = [];
    let rawBlock = null;

    for (let index = 0; index < lines.length; index++) {
        if (structure.frontMatterLines.has(index)) {
            continue;
        }

        const line = lines[index];
        const inlineCodeRanges = structure.codeLines.has(index) ? [[0, line.length]] : findInlineCodeRanges(line);
        let cursor = 0;
        while (cursor < line.length) {
            if (rawBlock) {
                const endRaw = line.slice(cursor).match(/\{%-?\s*endraw\s*-?%\}/);
                if (!endRaw) {
                    break;
                }
                cursor += endRaw.index + endRaw[0].length;
                rawBlock = null;
                continue;
            }

            const nextOutput = line.indexOf('{{', cursor);
            const nextTag = line.indexOf('{%', cursor);
            const positions = [nextOutput, nextTag].filter(position => position !== -1);
            if (positions.length === 0) {
                break;
            }

            const position = Math.min(...positions);
            if (position === nextTag) {
                const closing = line.indexOf('%}', position + 2);
                const fullTag = closing === -1 ? line.slice(position) : line.slice(position, closing + 2);
                const body = fullTag
                    .replace(/^\{%-?/, '')
                    .replace(/-?%\}$/, '')
                    .trim();
                const tagName = body.split(/\s+/, 1)[0];
                if (tagName === 'raw') {
                    rawBlock = { column: position + 1, line: index + 1 };
                } else if (tagName === 'endraw') {
                    diagnostics.push(createDiagnostic(file, index + 1, position + 1, 'liquid-raw', 'encountered endraw without a matching raw tag'));
                } else if (isLiteralLiquid(position, inlineCodeRanges) || !ACTIVE_LIQUID_TAGS.has(tagName)) {
                    diagnostics.push(liquidLiteralDiagnostic(file, index + 1, position + 1));
                }
                cursor = closing === -1 ? line.length : closing + 2;
                continue;
            }

            const closing = line.indexOf('}}', position + 2);
            const body = line.slice(position + 2, closing === -1 ? line.length : closing).replace(/^-|-$/g, '').trim();
            if (isLiteralLiquid(position, inlineCodeRanges) || !ACTIVE_LIQUID_OUTPUT.test(body)) {
                diagnostics.push(liquidLiteralDiagnostic(file, index + 1, position + 1));
            }
            cursor = closing === -1 ? line.length : closing + 2;
        }
    }

    if (rawBlock) {
        diagnostics.push(createDiagnostic(file, rawBlock.line, rawBlock.column, 'liquid-raw', 'raw block was never closed with an endraw tag'));
    }

    return diagnostics;
}

function liquidLiteralDiagnostic(file, line, column) {
    return createDiagnostic(
        file,
        line,
        column,
        'liquid-literal',
        'literal Liquid delimiter is unprotected; wrap the example in a raw/endraw block or encode the braces',
    );
}

function isLiteralLiquid(position, inlineCodeRanges) {
    return inlineCodeRanges.some(([start, end]) => position >= start && position < end);
}

function findInlineCodeRanges(line) {
    const ranges = [];
    let cursor = 0;
    while (cursor < line.length) {
        if (line[cursor] !== '`') {
            cursor++;
            continue;
        }

        let runLength = 1;
        while (line[cursor + runLength] === '`') {
            runLength++;
        }
        const delimiter = '`'.repeat(runLength);
        const closing = line.indexOf(delimiter, cursor + runLength);
        if (closing === -1) {
            cursor += runLength;
            continue;
        }
        ranges.push([cursor, closing + runLength]);
        cursor = closing + runLength;
    }
    return ranges;
}

function maskInlineCode(line) {
    const characters = [...line];
    for (const [start, end] of findInlineCodeRanges(line)) {
        for (let index = start; index < end; index++) {
            characters[index] = ' ';
        }
    }
    return characters.join('');
}

function stripInlineMarkdown(value) {
    return value
        .replace(/\s*\{(?::\s*)?#[A-Za-z][\w:.-]*[^}]*\}\s*$/, '')
        .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
        .replace(/\[([^\]]+)\]\[[^\]]*\]/g, '$1')
        .replace(/<[^>]+>/g, '')
        .replace(/`+([^`]*)`+/g, '$1')
        .replace(/\\([\\`*_[\]{}()#+\-.!])/g, '$1')
        .replace(/[*~]/g, '');
}

function decodeHtmlEntities(value) {
    const named = new Map([
        ['amp', '&'],
        ['apos', "'"],
        ['gt', '>'],
        ['lt', '<'],
        ['quot', '"'],
    ]);

    return value.replace(/&(#x[\da-f]+|#\d+|[a-z]+);/gi, (match, entity) => {
        if (entity[0] !== '#') {
            return named.get(entity.toLowerCase()) ?? match;
        }
        const hexadecimal = entity[1].toLowerCase() === 'x';
        const parsed = Number.parseInt(entity.slice(hexadecimal ? 2 : 1), hexadecimal ? 16 : 10);
        return Number.isFinite(parsed) ? String.fromCodePoint(parsed) : match;
    });
}

function unescapeMarkdownTarget(value) {
    return value.replace(/\\([\\`*_[\]{}()#+\-.!])/g, '$1');
}

function findUnescaped(value, needle, start) {
    for (let index = start; index < value.length; index++) {
        if (value[index] === needle && value[index - 1] !== '\\') {
            return index;
        }
    }
    return -1;
}

function decodeUrlComponent(value) {
    try {
        return decodeURIComponent(value);
    } catch {
        return null;
    }
}

function createDiagnostic(file, line, column, rule, message) {
    return { column, file, line, message, rule };
}

function compareDiagnostics(left, right) {
    return left.file.localeCompare(right.file)
        || left.line - right.line
        || left.column - right.column
        || left.rule.localeCompare(right.rule)
        || left.message.localeCompare(right.message);
}

function isIgnoredLine(index, structure) {
    return structure.frontMatterLines.has(index) || structure.codeLines.has(index);
}

function isMarkdown(file) {
    return MARKDOWN_EXTENSIONS.has(extname(file).toLowerCase());
}

function isGeneratedMarkdown(file) {
    return isGeneratedApiPath(file);
}

function isGeneratedApiPath(file) {
    return GENERATED_API_PREFIXES.some(prefix => file.startsWith(prefix));
}

function isPublishedMarkdown(file) {
    return file.startsWith('docs/') && extname(file).toLowerCase() === '.md' && !isGeneratedMarkdown(file);
}

function listRepositoryPaths(repoRoot) {
    const result = spawnSync('git', ['-C', repoRoot, 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], {
        encoding: 'utf8',
        maxBuffer: 32 * 1024 * 1024,
    });
    if (result.error) {
        throw new Error(`unable to run git ls-files: ${result.error.message}`);
    }
    if (result.status !== 0) {
        throw new Error(`git ls-files failed: ${result.stderr.trim() || `exit ${result.status}`}`);
    }
    return result.stdout.split('\0').filter(Boolean).sort();
}

function isWithinRoot(root, target) {
    return target === root || target.startsWith(`${root}/`);
}

function repositoryRoot() {
    return resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
}

function main() {
    try {
        const result = validateRepository(repositoryRoot());
        if (result.diagnostics.length > 0) {
            for (const diagnostic of result.diagnostics) {
                console.error(formatDiagnostic(diagnostic));
            }
            console.error(`Documentation integrity check failed with ${result.diagnostics.length} issue(s) across ${result.markdownCount} repository Markdown file(s).`);
            process.exitCode = 1;
            return;
        }
        console.log(`Documentation integrity check passed: ${result.markdownCount} repository Markdown file(s) scanned.`);
    } catch (error) {
        console.error(`Documentation integrity check could not run: ${error.message}`);
        process.exitCode = 2;
    }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    main();
}
