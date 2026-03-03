const fsp = require('fs/promises');
const path = require('path');

function createCloudProvider(storageRoot) {
  const cloudRoot = path.join(storageRoot, 'cloud-server');

  return {
    name: 'cloudServer',
    async uploadFinalFile(localFilePath, fileId) {
      await fsp.mkdir(cloudRoot, { recursive: true });
      const destination = path.join(cloudRoot, `${fileId}.mp4`);
      await fsp.copyFile(localFilePath, destination);

      return {
        provider: 'cloudServer',
        location: destination,
      };
    },
  };
}

module.exports = {
  createCloudProvider,
};
