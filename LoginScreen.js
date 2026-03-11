import React, { useState } from 'react';
import { View, Text, TextInput, Button, StyleSheet, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BASE_URL } from './config';

const LoginScreen = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const navigation = useNavigation();

  const handleLogin = async () => {
    if (!username || !password) {
      Alert.alert('Error', 'Please enter both username and password');
      return;
    }

    try {
      //Ada's comment: IMPORTANT!!
      // When testing, please change the 172.21.161.56 to your computer local IP, which
      // can gain by input 'ipconfig getifaddr en0' in to the terminal of your computer
      const response = await fetch(`${BASE_URL}/WesDashAPI/login.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          username: username,
          password: password,
        }),
      });

      // Log the raw response for debugging
      const text = await response.text();
      console.log("Raw response:", text);

      // Try parsing the response
      try {
        const data = JSON.parse(text);
        if (data.success) {
          await AsyncStorage.setItem("PHPSESSID", data.session_id);
          Alert.alert('Success', 'Login successful!');
          navigation.navigate('Dashboard', { username, password });
        } else {
          Alert.alert('Error', data.message);
        }
      } catch (jsonError) {
        console.error("JSON Parse Error:", jsonError);
        Alert.alert('Error', 'Unexpected response from server.');
      }
    } catch (error) {
      console.error(error);
      Alert.alert('Error', 'Something went wrong.');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Login</Text>
      <TextInput
        style={styles.input}
        placeholder="Username"
        value={username}
        onChangeText={setUsername}
      />
      <TextInput
        style={styles.input}
        placeholder="Password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      <Button title="Login" onPress={handleLogin} />
      <Button
        title="Go to Register"
        onPress={() => navigation.navigate('Register')}
      />
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
  input: {
    height: 40,
    borderColor: '#ccc',
    borderWidth: 1,
    marginBottom: 10,
    paddingLeft: 8,
    width: '100%',
  },
});

export default LoginScreen;
