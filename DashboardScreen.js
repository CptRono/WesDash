import React, { useState } from "react";
import { View, Text, Button, StyleSheet,TextInput } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { BASE_URL } from './config';

const DashboardScreen = ({ route, navigation }) => {
  const { username, password } = route.params;
  const [showDeleteFields, setShowDeleteFields] = useState(false);
  const [passwordToDelete, setPasswordToDelete] = useState('');
  const [confirmPasswordToDelete, setConfirmPasswordToDelete] = useState('');

  const handleLogout = () => {
    navigation.navigate('Home');
  };
  const handleDeleteAccount = async () => {
      if (passwordToDelete !== confirmPasswordToDelete) {
        Alert.alert("Error", "Passwords do not match.");
        return;
      }

      try {
        const response = await fetch(`${BASE_URL}/WesDashAPI/delete_user.php`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ password: passwordToDelete }),
          credentials: "include",
        });

        const data = await response.json();

        if (data.success) {
          Alert.alert("Account Deleted", "Your account has been deleted successfully.");
          navigation.navigate("Home");
        } else {
          Alert.alert("Error", data.message || "Failed to delete account.");
        }
      } catch (error) {
        console.error("Error deleting account:", error);
        Alert.alert("Error", "Something went wrong. Please try again.");
      }
    };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Dashboard</Text>
      <Text style={styles.subtitle}>Welcome to your dashboard!</Text>
      <Button title="Logout" onPress={handleLogout} />
      <Button title="Create Request" onPress={() => navigation.navigate('CreateRequestScreen')} />
      <Button title="View Request" onPress={() => navigation.navigate('ViewRequestScreen')} />
      <Button title="Accept Order" onPress={() => navigation.navigate('AcceptOrderScreen')} />
      <Button
        title="Delete Account"
        color="red"
        onPress={() => setShowDeleteFields(!showDeleteFields)}
      />

      {showDeleteFields && (
        <>
          <TextInput
            style={styles.input}
            placeholder="Enter Password"
            secureTextEntry
            value={passwordToDelete}
            onChangeText={setPasswordToDelete}
          />
          <TextInput
            style={styles.input}
            placeholder="Confirm Password"
            secureTextEntry
            value={confirmPasswordToDelete}
            onChangeText={setConfirmPasswordToDelete}
          />
          <Button title="Confirm Deletion" onPress={handleDeleteAccount} color="red" />
        </>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#f4f4f9',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  subtitle: {
    fontSize: 16,
    marginBottom: 20,
    color: '#555',
  },
});

export default DashboardScreen;
