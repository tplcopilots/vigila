const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');

class ChunkStore {
  constructor(storageRoot) {
    this.storageRoot = storageRoot;
    this.chunksRoot = path.join(storageRoot, 'chunks');
    this.finalRoot = path.join(storageRoot, 'final');
    this.reportRoot = path.join(storageRoot, 'reports');
  }

  async initialize() {
    await fsp.mkdir(this.chunksRoot, { recursive: true });
    await fsp.mkdir(this.finalRoot, { recursive: true });
    await fsp.mkdir(this.reportRoot, { recursive: true });
  }

  async saveReport(reportPayload) {
    const file = path.join(this.reportRoot, 'reports.jsonl');
    const line = `${JSON.stringify(reportPayload)}\n`;
    await fsp.appendFile(file, line, 'utf8');
  }

  async saveChunk({ fileId, chunkIndex, payloadBase64 }) {
    const dir = path.join(this.chunksRoot, fileId);
    await fsp.mkdir(dir, { recursive: true });

    const partPath = path.join(dir, `${chunkIndex}.part`);
    const alreadyExists = await fsp
      .access(partPath)
      .then(() => true)
      .catch(() => false);

    if (alreadyExists) {
      const duplicateError = new Error('Chunk already exists');
      duplicateError.code = 'DUPLICATE_CHUNK';
      throw duplicateError;
    }

    const bytes = Buffer.from(payloadBase64, 'base64');
    await fsp.writeFile(partPath, bytes);

    return partPath;
  }

  async getUploadedChunkIndexes(fileId) {
    const dir = path.join(this.chunksRoot, fileId);
    const exists = await fsp
      .access(dir)
      .then(() => true)
      .catch(() => false);

    if (!exists) {
      return [];
    }

    const names = await fsp.readdir(dir);
    return names
      .filter((name) => name.endsWith('.part'))
      .map((name) => Number(name.replace('.part', '')))
      .filter((index) => Number.isInteger(index) && index >= 0)
      .sort((a, b) => a - b);
  }

  async getMissingChunkIndexes(fileId, totalChunks) {
    const uploaded = await this.getUploadedChunkIndexes(fileId);
    const uploadedSet = new Set(uploaded);
    const missing = [];

    for (let i = 0; i < totalChunks; i++) {
      if (!uploadedSet.has(i)) {
        missing.push(i);
      }
    }

    return {
      uploaded,
      missing,
    };
  }

  async joinChunks({ fileId, totalChunks }) {
    const dir = path.join(this.chunksRoot, fileId);
    const outputPath = path.join(this.finalRoot, `${fileId}.mp4`);

    await new Promise((resolve, reject) => {
      const write = fs.createWriteStream(outputPath);

      write.on('error', reject);
      write.on('finish', resolve);

      (async () => {
        try {
          for (let i = 0; i < totalChunks; i++) {
            const partPath = path.join(dir, `${i}.part`);
            await fsp.access(partPath);

            await new Promise((res, rej) => {
              const read = fs.createReadStream(partPath);
              read.on('error', rej);
              read.on('end', res);
              read.pipe(write, { end: false });
            });
          }
          write.end();
        } catch (error) {
          reject(error);
        }
      })();
    });

    return outputPath;
  }

  async deleteChunkDirectory(fileId) {
    const dir = path.join(this.chunksRoot, fileId);
    await fsp.rm(dir, { recursive: true, force: true });
  }
}

module.exports = {
  ChunkStore,
};
