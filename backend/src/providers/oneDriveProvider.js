const fs = require('fs/promises');

function createOneDriveProvider(config) {
  return {
    name: 'oneDrive',
    async uploadFinalFile(localFilePath, fileId) {
      if (!config.oneDriveAccessToken) {
        throw new Error('ONEDRIVE_ACCESS_TOKEN is required for oneDrive provider');
      }

      const bytes = await fs.readFile(localFilePath);
      const uploadUrl = `https://graph.microsoft.com/v1.0/me/drive/root:/${config.oneDriveFolder}/${fileId}.mp4:/content`;

      const response = await fetch(uploadUrl, {
        method: 'PUT',
        headers: {
          Authorization: `Bearer ${config.oneDriveAccessToken}`,
          'Content-Type': 'video/mp4',
        },
        body: bytes,
      });

      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(`OneDrive upload failed: ${response.status} ${JSON.stringify(payload)}`);
      }

      return {
        provider: 'oneDrive',
        location: payload.webUrl || payload.id || 'uploaded',
      };
    },
  };
}

module.exports = {
  createOneDriveProvider,
};
