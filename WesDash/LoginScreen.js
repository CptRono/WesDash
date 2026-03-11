import React, { useState } from 'react';
import { View, Text, TextInput, Button, TouchableOpacity, StyleSheet, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BASE_URL } from '../config';

const LoginScreen = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [role,setRole]          = useState('user');
  const navigation = useNavigation();

const handleLogin = async () => {
  if (!username || !password) {
    Alert.alert('Error', 'Please enter both username and password');
    return;
  }

  try {//Ada's comment: IMPORTANT!!
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
        role
      }),
    });

    const text = await response.text();
    console.log("Raw response:", text);

      const data = JSON.parse(text);
      if (data.success) {
        await AsyncStorage.setItem('PHPSESSID', data.session_id);
        // store username & role for HomeScreen (and anywhere else)
        await AsyncStorage.setItem('username', username);
        await AsyncStorage.setItem('role', role);
        Alert.alert('Success', 'Login successful!');
        navigation.navigate('Dashboard', { username, role });
      } else {
        Alert.alert('Error', data.message);
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
        autoCapitalize="none"
      />
      <TextInput
        style={styles.input}
        placeholder="Password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />

      <View style={styles.roleContainer}>
        <TouchableOpacity
          style={[
            styles.roleButton,
            role === 'user' && styles.roleButtonSelected
          ]}
          onPress={() => setRole('user')}
        >
          <Text
            style={[
              styles.roleText,
              role === 'user' && styles.roleTextSelected
            ]}
          >
            User
          </Text>
        </TouchableOpacity>


        <TouchableOpacity
          style={[
            styles.roleButton,
            role === 'dasher' && styles.roleButtonSelected
          ]}
          onPress={() => setRole('dasher')}
        >
          <Text
            style={[
              styles.roleText,
              role === 'dasher' && styles.roleTextSelected
            ]}
          >
            Dasher
          </Text>
        </TouchableOpacity>
      </View>


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
  roleContainer: {
    flexDirection: 'row',
    marginBottom: 20,
  },
  roleButton: {
    flex: 1,
    paddingVertical: 10,
    marginHorizontal: 5,
    borderWidth: 1,
    borderColor: '#999',
    borderRadius: 4,
    alignItems: 'center',
  },
  roleButtonSelected: {
    backgroundColor: '#007bff',
    borderColor: '#0056b3',
  },
  roleText: {
    color: '#333',
    fontSize: 16,
  },
  roleTextSelected: {
    color: 'white',
    fontWeight: 'bold',
  },
});

export default LoginScreen;