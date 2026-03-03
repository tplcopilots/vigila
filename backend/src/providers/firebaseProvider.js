const admin = require('firebase-admin');

function createFirebaseProvider(config) {
  let initialized = false;

  function ensureInitialized() {
    if (initialized) {
      return;
    }

    if (!config.firebaseStorageBucket) {
      throw new Error('FIREBASE_STORAGE_BUCKET is required for firebase provider');
    }

    if (!admin.apps.length) {
      admin.initializeApp({
        storageBucket: config.firebaseStorageBucket,
      });
    }

    initialized = true;
  }

  return {
    name: 'firebase',
    async uploadFinalFile(localFilePath, fileId) {
      ensureInitialized();
      const bucket = admin.storage().bucket();
      const destination = `vigilance-videos/${fileId}.mp4`;

      await bucket.upload(localFilePath, {
        destination,
        metadata: {
          contentType: 'video/mp4',
        },
      });

      const file = bucket.file(destination);
      await file.makePublic();
      const publicUrl = `https://storage.googleapis.com/${bucket.name}/${destination}`;

      return {
        provider: 'firebase',
        location: publicUrl,
      };
    },
  };
}

module.exports = {
  createFirebaseProvider,
};
