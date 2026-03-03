const { createCloudProvider } = require('./cloudProvider');
const { createFirebaseProvider } = require('./firebaseProvider');
const { createOneDriveProvider } = require('./oneDriveProvider');

function getProvider(providerName, config) {
  switch (providerName) {
    case 'firebase':
      return createFirebaseProvider(config);
    case 'oneDrive':
      return createOneDriveProvider(config);
    case 'cloudServer':
      return createCloudProvider(config.storageRoot);
    default:
      throw new Error(`Unsupported provider: ${providerName}`);
  }
}

module.exports = {
  getProvider,
};
