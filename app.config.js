export default ({ config }) => {
  const platform = process.env.EXPO_OS; // "ios" | "android" | "web" (often set by Expo)
  return {
    ...config,
    plugins: [
      ...(config.plugins ?? []).filter(p => !(Array.isArray(p) ? p[0] === "@stripe/stripe-react-native" : p === "@stripe/stripe-react-native")),
      ...(platform === "web"
        ? []
        : [[
            "@stripe/stripe-react-native",
            { merchantIdentifier: "merchant.com.yourapp" }
          ]])
    ],
  };
};
