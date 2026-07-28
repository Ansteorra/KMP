#!/usr/bin/env node

import { readFile } from 'node:fs/promises'

const [notesPath, releaseName, releaseUrl] = process.argv.slice(2)
if (!notesPath || !releaseName || !releaseUrl) {
    throw new Error('Usage: post-release-to-discord.mjs <notes-file> <release-name> <release-url>')
}

const notes = (await readFile(notesPath, 'utf8')).trim()
if (!notes) {
    throw new Error('Release notes are empty')
}

const chunkText = (text, limit = 1900) => {
    const chunks = []
    let remaining = text
    while (remaining.length > limit) {
        let splitAt = remaining.lastIndexOf('\n', limit)
        if (splitAt < Math.floor(limit / 2)) {
            splitAt = limit
        }
        chunks.push(remaining.slice(0, splitAt))
        remaining = remaining.slice(splitAt)
    }
    if (remaining) {
        chunks.push(remaining)
    }
    return chunks
}

const payloads = [
    {
        content: `# ${releaseName}\n${releaseUrl}`,
        allowed_mentions: { parse: [] }
    },
    ...chunkText(notes).map(content => ({
        content,
        allowed_mentions: { parse: [] }
    }))
]

if (process.env.DISCORD_DRY_RUN === 'true') {
    process.stdout.write(`${JSON.stringify(payloads, null, 2)}\n`)
    process.exit(0)
}

const webhookValue = process.env.DISCORD_RELEASE_WEBHOOK_URL
if (!webhookValue) {
    throw new Error('DISCORD_RELEASE_WEBHOOK_URL is not configured')
}

const webhookUrl = new URL(webhookValue)
const validDiscordHost = webhookUrl.hostname === 'discord.com' || webhookUrl.hostname === 'discordapp.com'
if (
    webhookUrl.protocol !== 'https:'
    || !validDiscordHost
    || !webhookUrl.pathname.startsWith('/api/webhooks/')
) {
    throw new Error('DISCORD_RELEASE_WEBHOOK_URL is not a Discord webhook URL')
}
webhookUrl.searchParams.set('wait', 'true')

const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds))

for (const payload of payloads) {
    let delivered = false
    for (let attempt = 1; attempt <= 4; attempt += 1) {
        const response = await fetch(webhookUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        if (response.ok) {
            delivered = true
            break
        }
        if (response.status !== 429 || attempt === 4) {
            const responseBody = await response.text()
            throw new Error(`Discord webhook returned HTTP ${response.status}: ${responseBody.slice(0, 300)}`)
        }
        const rateLimit = await response.json()
        await sleep(Math.ceil(Number(rateLimit.retry_after || 1) * 1000))
    }
    if (!delivered) {
        throw new Error('Discord release announcement could not be delivered')
    }
}
