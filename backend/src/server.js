require('dotenv').config();

const crypto = require('crypto');
const express = require('express');
const cors = require('cors');
const morgan = require('morgan');

const { loadConfig } = require('./config');
const { ChunkStore } = require('./chunkStore');
const { getProvider } = require('./providers');

function sha256Hex(input) {
  return crypto.createHash('sha256').update(input).digest('hex');
}

function hmacSha256Hex(secret, message) {
  return crypto.createHmac('sha256', secret).update(message).digest('hex');
}

function safeEqualsHex(a, b) {
  if (!a || !b || a.length !== b.length) {
    return false;
  }

  const aBuffer = Buffer.from(a, 'utf8');
  const bBuffer = Buffer.from(b, 'utf8');
  return crypto.timingSafeEqual(aBuffer, bBuffer);
}

async function start() {
  const config = loadConfig();
  const chunkStore = new ChunkStore(config.storageRoot);
  await chunkStore.initialize();
  const seenNonces = new Map();

  const app = express();
  app.use(cors());
  app.use(morgan('dev'));
  app.use(
    express.json({
      limit: '15mb',
      verify: (req, _res, buffer) => {
        req.rawBody = buffer.toString('utf8');
      },
    }),
  );

  app.use('/api', (req, res, next) => {
    const incomingApiKey = req.header('x-api-key');
    if (!incomingApiKey || incomingApiKey !== config.apiKey) {
      return res.status(401).json({
        error: 'Unauthorized',
      });
    }

    const timestampHeader = req.header('x-timestamp');
    const nonceHeader = req.header('x-nonce');
    const signatureHeader = req.header('x-signature');
    if (!timestampHeader || !nonceHeader || !signatureHeader) {
      return res.status(401).json({
        error: 'Missing signature headers',
      });
    }

    const timestamp = Number(timestampHeader);
    if (!Number.isInteger(timestamp)) {
      return res.status(401).json({
        error: 'Invalid timestamp',
      });
    }

    const now = Date.now();
    if (Math.abs(now - timestamp) > config.signatureMaxSkewMs) {
      return res.status(401).json({
        error: 'Expired request timestamp',
      });
    }

    for (const [nonce, expiryTime] of seenNonces.entries()) {
      if (expiryTime <= now) {
        seenNonces.delete(nonce);
      }
    }

    if (seenNonces.has(nonceHeader)) {
      return res.status(409).json({
        error: 'Replay detected',
      });
    }

    const body = req.rawBody || '';
    const bodyHash = sha256Hex(body);
    const canonicalPath = req.originalUrl.split('?')[0];
    const canonical = [
      req.method.toUpperCase(),
      canonicalPath,
      String(timestamp),
      nonceHeader,
      bodyHash,
    ].join('\n');
    const expectedSignature = hmacSha256Hex(
      config.requestSigningSecret,
      canonical,
    );

    if (!safeEqualsHex(signatureHeader, expectedSignature)) {
      return res.status(401).json({
        error: 'Invalid signature',
      });
    }

    seenNonces.set(nonceHeader, now + config.signatureMaxSkewMs);
    return next();
  });

  app.get('/health', (_, res) => {
    res.json({
      ok: true,
      provider: config.activeStorageProvider,
      timestamp: new Date().toISOString(),
    });
  });

  app.post('/api/report', async (req, res) => {
    try {
      const { reportId, report, provider } = req.body || {};

      if (!reportId || !report) {
        return res.status(400).json({
          error: 'reportId and report are required',
        });
      }

      await chunkStore.saveReport({
        reportId,
        report,
        provider: provider || config.activeStorageProvider,
        createdAt: new Date().toISOString(),
      });

      return res.status(200).json({
        success: true,
        reportId,
      });
    } catch (error) {
      return res.status(500).json({
        error: error.message,
      });
    }
  });

  app.post('/api/upload/chunk', async (req, res) => {
    try {
      const { fileId, chunkIndex, totalChunks, payload } = req.body || {};

      if (!fileId || chunkIndex === undefined || !totalChunks || !payload) {
        return res.status(400).json({
          error: 'fileId, chunkIndex, totalChunks, and payload are required',
        });
      }

      const parsedChunkIndex = Number(chunkIndex);
      const parsedTotalChunks = Number(totalChunks);
      if (
        !Number.isInteger(parsedChunkIndex) ||
        parsedChunkIndex < 0 ||
        !Number.isInteger(parsedTotalChunks) ||
        parsedTotalChunks <= 0 ||
        parsedChunkIndex >= parsedTotalChunks
      ) {
        return res.status(400).json({
          error: 'Invalid chunkIndex or totalChunks value',
        });
      }

      await chunkStore.saveChunk({
        fileId,
        chunkIndex: parsedChunkIndex,
        payloadBase64: payload,
      });

      return res.status(200).json({
        success: true,
        fileId,
        chunkIndex: parsedChunkIndex,
      });
    } catch (error) {
      if (error.code === 'DUPLICATE_CHUNK') {
        return res.status(409).json({
          error: 'Chunk already uploaded',
        });
      }
      return res.status(500).json({
        error: error.message,
      });
    }
  });

  app.get('/api/upload/status', async (req, res) => {
    try {
      const { fileId, totalChunks } = req.query || {};

      if (!fileId || !totalChunks) {
        return res.status(400).json({
          error: 'fileId and totalChunks are required',
        });
      }

      const parsedTotalChunks = Number(totalChunks);
      if (!Number.isInteger(parsedTotalChunks) || parsedTotalChunks <= 0) {
        return res.status(400).json({
          error: 'totalChunks must be a positive integer',
        });
      }

      const status = await chunkStore.getMissingChunkIndexes(
        fileId,
        parsedTotalChunks,
      );

      return res.status(200).json({
        success: true,
        fileId,
        totalChunks: parsedTotalChunks,
        uploadedIndexes: status.uploaded,
        missingIndexes: status.missing,
      });
    } catch (error) {
      return res.status(500).json({
        error: error.message,
      });
    }
  });

  app.post('/api/upload/finalize', async (req, res) => {
    try {
      const { fileId, totalChunks, provider } = req.body || {};

      if (!fileId || !totalChunks) {
        return res.status(400).json({
          error: 'fileId and totalChunks are required',
        });
      }

      const parsedTotalChunks = Number(totalChunks);
      if (!Number.isInteger(parsedTotalChunks) || parsedTotalChunks <= 0) {
        return res.status(400).json({
          error: 'totalChunks must be a positive integer',
        });
      }

      const status = await chunkStore.getMissingChunkIndexes(fileId, parsedTotalChunks);
      if (status.missing.length > 0) {
        return res.status(409).json({
          error: 'Cannot finalize upload. Missing chunks exist.',
          missingIndexes: status.missing,
          uploadedIndexes: status.uploaded,
        });
      }

      const mergedFile = await chunkStore.joinChunks({
        fileId,
        totalChunks: parsedTotalChunks,
      });

      const activeProvider = provider || config.activeStorageProvider;
      const providerImpl = getProvider(activeProvider, config);
      const uploadResult = await providerImpl.uploadFinalFile(mergedFile, fileId);

      await chunkStore.deleteChunkDirectory(fileId);

      return res.status(200).json({
        success: true,
        fileId,
        provider: activeProvider,
        storage: uploadResult,
      });
    } catch (error) {
      return res.status(500).json({
        error: error.message,
      });
    }
  });

  app.listen(config.port, () => {
    console.log(`Vigilance upload server running on http://localhost:${config.port}`);
  });
}

start().catch((error) => {
  console.error('Failed to start server', error);
  process.exit(1);
});
