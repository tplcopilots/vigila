const path = require('path');

function loadConfig() {
  const storageRoot = process.env.STORAGE_ROOT || './storage';

  return {
    port: Number(process.env.PORT || 8080),
    apiKey: process.env.API_KEY || 'change-me-api-key',
    requestSigningSecret:
      process.env.REQUEST_SIGNING_SECRET || 'change-me-signing-secret',
    signatureMaxSkewMs: Number(process.env.SIGNATURE_MAX_SKEW_MS || 300000),
    activeStorageProvider: process.env.ACTIVE_STORAGE_PROVIDER || 'firebase',
    oneDriveAccessToken: process.env.ONEDRIVE_ACCESS_TOKEN || '',
    oneDriveFolder: process.env.ONEDRIVE_FOLDER || 'vigilance-videos',
    firebaseStorageBucket: process.env.FIREBASE_STORAGE_BUCKET || '',
    storageRoot: path.resolve(process.cwd(), storageRoot),
  };
}

module.exports = {
  loadConfig,
};
