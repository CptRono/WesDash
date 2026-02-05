const { getDefaultConfig } = require("expo/metro-config");

const config = getDefaultConfig(__dirname);

// Add web-specific platform extensions
config.resolver.platforms = ['web', 'ios', 'android'];
config.resolver.sourceExts = [...config.resolver.sourceExts, 'jsx', 'js', 'ts', 'tsx', 'json'];

// Alias react-native to react-native-web for web platform
config.resolver.resolveRequest = (context, moduleName, platform) => {
  if (platform === 'web') {
    // Alias react-native to react-native-web
    if (moduleName === 'react-native') {
      return context.resolveRequest(context, 'react-native-web', platform);
    }
    // Handle react-native internal imports by redirecting to react-native-web
    if (moduleName.startsWith('react-native/')) {
      const webModule = moduleName.replace('react-native/', 'react-native-web/dist/');
      try {
        return context.resolveRequest(context, webModule, platform);
      } catch (e) {
        // Fall back to react-native-web root
        return context.resolveRequest(context, 'react-native-web', platform);
      }
    }
  }
  // Default resolver
  return context.resolveRequest(context, moduleName, platform);
};

module.exports = config;
