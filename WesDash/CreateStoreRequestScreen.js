import React, { useState, useEffect } from "react";
import { View, Text, TextInput, Button, Alert, StyleSheet, Image } from "react-native";
import { BASE_URL } from '../config';

const CreateStoreRequestScreen = ({ navigation, route }) => {
  // Check if product data was passed from SearchScreen
  const productData = route.params?.productData;
  
  const [item, setItem] = useState(productData?.item_name || "");
  const [dropOffLocation, setDropOffLocation] = useState("");
  const [deliverySpeed, setDeliverySpeed] = useState("common");
  const [productImage, setProductImage] = useState(productData?.image_url || null);

  const handleSubmit = async () => {
    if (!item || !dropOffLocation) {
      Alert.alert("Error", "Item and Drop-off Location cannot be empty!");
      return;
    }

    try {
      // Prepare request data, including product_id if available
      const requestData = {
        item,
        drop_off_location: dropOffLocation,
        delivery_speed: deliverySpeed,
      };
      
      // Add product_id if it was provided from the SearchScreen
      if (productData?.product_id) {
        requestData.product_id = productData.product_id;
      }
      
      // Add image_url if available
      if (productImage) {
        requestData.image_url = productImage;
      }

      const response = await fetch(`${BASE_URL}/WesDashAPI/create_requests.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: 'include',
        body: JSON.stringify(requestData),
      });

      const text = await response.text();
      console.log("Raw response:", text);

      try {
        const data = JSON.parse(text);

        if (response.ok && data.success) {
          // Get the route params from the current screen that might have the username and role
          const { username, role } = route.params || {};
          
          Alert.alert("Success", data.success, [
            {
              text: "OK",
              onPress: () => {
                // If we have username and role, pass them back to Dashboard
                if (username && role) {
                  navigation.navigate("Dashboard", { username, role });
                } else {
                  navigation.navigate("Dashboard");
                }
              }
            }
          ]);
        } else {
          Alert.alert("Error", data.error || "Failed to create request.");
        }
      } catch (jsonError) {
        console.error("JSON Parse Error:", jsonError);
        Alert.alert("Error", "Unexpected response from server.");
      }
    } catch (error) {
      console.error("Request failed", error);
      Alert.alert("Error", "Failed to create request. Please try again.");
    }
  };

  return (
    <View style={styles.container}>
      {/* Show product image if available */}
      {productImage && (
        <View style={styles.imageContainer}>
          <Image 
            source={{ uri: productImage }} 
            style={styles.productImage} 
            resizeMode="contain"
          />
          {productData?.product_id && (
            <Text style={styles.productId}>Product ID: {productData.product_id}</Text>
          )}
        </View>
      )}

      <Text style={styles.label}>Item:</Text>
      <TextInput 
        style={styles.input} 
        value={item} 
        onChangeText={setItem} 
        placeholder="Enter item name"
      />

      <Text style={styles.label}>Drop-off Location:</Text>
      <TextInput
        style={styles.input}
        value={dropOffLocation}
        onChangeText={setDropOffLocation}
        placeholder="Enter delivery location (e.g., Fauver, Butts, Clark)"
      />

      <Text style={styles.label}>Delivery Speed:</Text>
      <View style={styles.radioGroup}>
        <Button
          title="Urgent"
          onPress={() => setDeliverySpeed("urgent")}
          color={deliverySpeed === "urgent" ? "blue" : "gray"}
        />
        <Button
          title="Common"
          onPress={() => setDeliverySpeed("common")}
          color={deliverySpeed === "common" ? "blue" : "gray"}
        />
      </View>

      <View style={styles.buttonContainer}>
        <Button 
          title="Create Request" 
          onPress={handleSubmit} 
          color="#3498db"
        />
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { 
    flex: 1, 
    padding: 20, 
    backgroundColor: "#fff" 
  },
  imageContainer: {
    alignItems: 'center',
    marginBottom: 15,
    marginTop: 10,
    padding: 10,
    backgroundColor: '#f8f8f8',
    borderRadius: 8,
  },
  productImage: {
    width: 150,
    height: 150,
    marginBottom: 10,
  },
  productId: {
    fontSize: 12,
    color: '#666',
    fontStyle: 'italic',
  },
  label: { 
    fontSize: 18, 
    fontWeight: "bold", 
    marginTop: 10 
  },
  input: {
    borderWidth: 1,
    borderColor: "#ccc",
    padding: 10,
    marginTop: 5,
    borderRadius: 5,
  },
  radioGroup: { 
    flexDirection: "row", 
    justifyContent: "space-around", 
    marginVertical: 15 
  },
  buttonContainer: {
    marginTop: 20,
  }
});

export default CreateStoreRequestScreen;
