import { importSPKI } from 'jose';
import { createGateway } from './gateway.js';
import { createRedisBroker } from './redis-broker.js';

const publicKey = await importSPKI(process.env.KMP_LIVE_PUBLIC_KEY, 'RS256');
const allowedOrigins = new Set((process.env.KMP_LIVE_ALLOWED_ORIGINS || '').split(',').map(value => value.trim()).filter(Boolean));
if (!allowedOrigins.size) throw new Error('Live gateway requires explicit allowed origins.');
const broker = await createRedisBroker(process.env.REDIS_URL, process.env.KMP_LIVE_PREFIX);
const server = createGateway({ broker, publicKey, allowedOrigins });
server.listen(Number(process.env.PORT || 8080), '0.0.0.0');
for (const signal of ['SIGTERM', 'SIGINT']) process.once(signal, async () => {
    await server.shutdown(); await broker.close();
});
