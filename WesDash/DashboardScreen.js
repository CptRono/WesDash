// screen/DashboardScreen.js
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Alert,
  Switch,
  TouchableOpacity,
  Image,
  Dimensions,
  TextInput,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BASE_URL } from '../config';

const RED      = '#C41E3A';
const RED_DARK = '#991427';
const BLUE     = '#5978FF';
const GREY_TXT = '#666';
const BG_COLOR = '#F3F4F8';

export default function DashboardScreen({ route, navigation }) {
  const { username = 'User', role: initRole = 'user' } = route.params || {};

  const [role, setRole] = useState(initRole);
  const [showDanger, setShowDanger] = useState(false);
  const [passwordToDelete, setPasswordToDelete] = useState('');
  const [confirmPasswordToDelete, setConfirmPasswordToDelete] = useState('');

  useEffect(() => {
    (async () => {
      const sid = await AsyncStorage.getItem('PHPSESSID');
      if (!sid) {
        Alert.alert('Error', 'Session ID not found. Please log in again.');
        return;
      }

      try {
        const response = await fetch(`${BASE_URL}/WesDashAPI/get_pending_review.php`, {
          method: 'GET',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
        });
        const data = await response.json();

        if (data.success && data.order) {
          navigation.navigate('CreateReviewScreen', { orderId: data.order.id });
        }
      } catch (error) {
        console.error('Error fetching pending review:', error);
      }
    })();
  }, []);

  /* ---- logout ---- */
  const handleLogout = () => navigation.navigate('Home');

  /* ---- delete ---- */
  const handleDeleteAccount = async () => {
    if (passwordToDelete !== confirmPasswordToDelete) {
      Alert.alert('Error', 'Passwords do not match.');
      return;
    }
    try {
      const resp = await fetch(`${BASE_URL}/WesDashAPI/delete_user.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: passwordToDelete }),
        credentials: 'include',
      });
      const data = await resp.json();
      if (data.success) {
        Alert.alert('Account Deleted', 'Your account has been deleted successfully.');
        navigation.navigate('Home');
      } else {
        Alert.alert('Error', data.message || 'Failed to delete account.');
      }
    } catch {
      Alert.alert('Error', 'Something went wrong. Please try again.');
    }
  };

  const BigButton = ({ title, onPress, color = RED }) => (
    <TouchableOpacity
      activeOpacity={0.85}
      onPress={onPress}
      style={[styles.bigBtn, { backgroundColor: color }]}
    >
      <Text style={styles.bigBtnTxt}>{title}</Text>
    </TouchableOpacity>
  );

  return (
    <View style={styles.root}>
      <Image source={require('../assets/cardinal.png')} style={styles.logo} />

      <Text style={styles.hi}>
        Hi, {username} <Text style={{ fontSize: 28 }}>👋</Text>
      </Text>

      <View style={styles.roleRow}>
        <Text style={styles.roleTxt}>Role: {role}</Text>
        <Switch
          value={role === 'dasher'}
          onValueChange={(v) => setRole(v ? 'dasher' : 'user')}
          thumbColor={RED}
          trackColor={{ false: '#ddd', true: '#fbd4d9' }}
        />
      </View>

      {role === 'user' ? (
        <>
          <BigButton
            title="Create Request"
            onPress={() => navigation.navigate('SearchScreen', { username, role })}
          />
          <BigButton
            title="View Request"
            onPress={() => navigation.navigate('ViewRequestScreen', { username, role })}
          />
        </>
      ) : (
        <BigButton
          title="Accept Orders"
          onPress={() => navigation.navigate('AcceptOrderScreen', { username, role })}
        />
      )}

      <BigButton
        title="Chat Rooms"
        color={BLUE}
        onPress={() => navigation.navigate('Chats', { username, role })}
      />

      {/* Danger Zone toggle */}
      <TouchableOpacity
        onPress={() => {
          setShowDanger(!showDanger);
          setPasswordToDelete('');
          setConfirmPasswordToDelete('');
        }}
        style={styles.dangerToggle}
      >
        <Text style={{ color: RED_DARK, fontSize: 15 }}>⚠️ Danger Zone</Text>
      </TouchableOpacity>

      {showDanger && (
        <View style={styles.deleteContainer}>
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
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={handleDeleteAccount}
            style={styles.deleteBtn}
          >
            <Text style={styles.deleteTxt}>Delete</Text>
          </TouchableOpacity>
        </View>
      )}

      <TouchableOpacity onPress={handleLogout} style={styles.logout}>
        <Text style={{ fontSize: 16, color: GREY_TXT }}>↩ Logout</Text>
      </TouchableOpacity>
    </View>
  );
}

/* ---- 样式 ---- */
const { height } = Dimensions.get('window');
const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: BG_COLOR,
    alignItems: 'center',
    paddingTop: height * 0.08,       
  },
  logo: { width: 90, height: 90, resizeMode: 'contain', marginBottom: 4 },
  hi: { fontSize: 34, fontWeight: '700', marginVertical: 10 },
  roleRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 26 },
  roleTxt: { fontSize: 17, color: GREY_TXT, marginRight: 8 },

  bigBtn: {
    width: '78%',
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginVertical: 8,
  },
  bigBtnTxt: { fontSize: 20, color: '#fff', fontWeight: '600' },

  dangerToggle: { marginTop: 20 },

  deleteContainer: {
    width: '80%',
    marginTop: 12,
    alignItems: 'center',
    paddingBottom: 180,  
  },
  input: {
    width: '100%',
    height: 42,
    borderColor: '#ccc',
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 10,
    marginBottom: 10,
    backgroundColor: '#fff',
  },
  deleteBtn: {
    width: '40%',          
    backgroundColor: '#444',
    paddingVertical: 9,    
    borderRadius: 10,
    alignItems: 'center',
  },
  deleteTxt: { fontSize: 16, color: '#fff', fontWeight: '600' },

  logout: { position: 'absolute', bottom: 20 },
});
