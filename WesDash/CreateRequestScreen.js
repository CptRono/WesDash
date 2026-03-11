// screen/CreateRequestScreen.js
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  Button,
  Alert,
  StyleSheet,
  ScrollView,
} from 'react-native';
import { Picker } from '@react-native-picker/picker';
import AsyncStorage from '@react-native-async-storage/async-storage';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { BASE_URL } from '../config';

/* ─────────────────────────────────────────────────────── */
const CreateRequestScreen = ({ route, navigation }) => {
  const { username = 'Unknown', role = 'user' } = route.params ?? {};

  /* ---------- state ---------- */
  const [items,        setItems]        = useState([]);
  const [selectedItem, setSelectedItem] = useState('');
  const [quantity,     setQuantity]     = useState('1');
  const [dropOff,      setDropOff]      = useState('');
  const [speed,        setSpeed]        = useState('common');
  const [sessionID,    setSessionID]    = useState(null);
  

  /* map */
  const [region, setRegion] = useState({
    latitude:       41.5556,     // Wesleyan U.
    longitude:     -72.6558,
    latitudeDelta:  0.02,
    longitudeDelta: 0.02,
  });
  const [marker, setMarker] = useState(null);

  /* ---------- fetch items ---------- */
  useEffect(() => {
    (async () => {
      const sid = await AsyncStorage.getItem('PHPSESSID');
      if (!sid) { Alert.alert('Error', 'Session ID not found'); return; }
      setSessionID(sid);

      try {
        const resp = await fetch(`${BASE_URL}/WesDashAPI/get_wesshop_items.php`, {
          credentials: 'include',
          headers: { Cookie: `PHPSESSID=${sid}` },
        });
        const data = await resp.json();
        if (!data.success) throw new Error();

        const list = Array.isArray(data.items) ? data.items : [];
        setItems(list);
        if (list.length) setSelectedItem(list[0].name);
      } catch {
        Alert.alert('Error', 'Could not load shop items.');
      }
    })();
  }, []);

  /* ---------- map tap ---------- */
  const handleMapPress = (e) => {
    const coord = e.nativeEvent.coordinate;
    setMarker(coord);
    setDropOff(`${coord.latitude}, ${coord.longitude}`);
  };

  /* ---------- submit ---------- */
  const handleSubmit = async () => {
    if (!selectedItem || !dropOff) {
      Alert.alert('Error', 'Item and drop-off location are required.');
      return;
    }

    const row  = items.find((i) => i.name === selectedItem);
    const qty  = parseInt(quantity, 10) || 1;

    const proceed = async () => {
      try {
        const resp = await fetch(`${BASE_URL}/WesDashAPI/create_requests.php`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            Cookie: `PHPSESSID=${sessionID}`,
          },
          body: JSON.stringify({
            item: selectedItem,
            quantity: qty,
            drop_off_location: dropOff,
            delivery_speed: speed,
          }),
        });
        const data = await resp.json();

        if (resp.ok && data.success) {
          Alert.alert('Success', data.success, [{ text: 'OK', onPress: () => navigation.goBack() }]);
        } else {
          throw new Error(data.error || 'Failed to create request.');
        }
      } catch (e) {
        Alert.alert('Error', e.message || 'Network error.');
      }
    };

    if (row && qty > row.number) {
      Alert.alert(
        'Warning',
        'Quantity exceeds current stock. Submit anyway?',
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Proceed', onPress: proceed },
        ],
      );
    } else {
      proceed();
    }
  };

  /* ---------- UI ---------- */
  return (
    <ScrollView style={styles.scroll} contentContainerStyle={styles.content}>

      <View style={styles.infoBox}>
        <Text style={styles.infoTxt}>Logged in as: {username}</Text>
        <Text style={styles.infoTxt}>
          Role: {role === 'dasher' ? 'Dasher' : 'User'}
        </Text>
      </View>

      {/* item */}
      <Text style={styles.label}>Item:</Text>
      <Picker selectedValue={selectedItem} onValueChange={setSelectedItem}>
        {items.map((i) => (
          <Picker.Item
            key={i.id}
            label={`${i.name} (stock: ${i.number})`}
            value={i.name}
          />
        ))}
      </Picker>

      {/* qty */}
      <Text style={styles.label}>Quantity:</Text>
      <TextInput
        style={styles.input}
        keyboardType="numeric"
        value={quantity}
        onChangeText={setQuantity}
      />

      {/* location */}
      <Text style={styles.label}>Drop-off (lat, lng):</Text>
      <TextInput
        style={styles.input}
        value={dropOff}
        onChangeText={setDropOff}
        placeholder="Tap the map or type coordinates"
      />

      <MapView
        style={styles.map}
        provider={PROVIDER_DEFAULT}
        region={region}
        onRegionChangeComplete={setRegion}
        onPress={handleMapPress}
      >
        {marker && <Marker coordinate={marker} />}
      </MapView>

      {/* speed */}
      <Text style={styles.label}>Delivery Speed:</Text>
      <View style={styles.radioRow}>
        {['urgent', 'common'].map((s) => (
          <Button
            key={s}
            title={s.charAt(0).toUpperCase() + s.slice(1)}
            onPress={() => setSpeed(s)}
            color={speed === s ? 'blue' : 'gray'}
          />
        ))}
      </View>

      <Button title="Create Request" onPress={handleSubmit} />

    </ScrollView>
  );
};

/* ---------- styles ---------- */
const styles = StyleSheet.create({
  scroll:  { flex: 1 },
  content: { padding: 20, backgroundColor: '#fff', flexGrow: 1 },

  infoBox: { marginBottom: 16, borderBottomWidth: 1, borderColor: '#eee', paddingBottom: 8 },
  infoTxt: { fontSize: 16, fontWeight: '500', color: '#333', marginBottom: 4 },

  label:   { fontSize: 18, fontWeight: 'bold', marginTop: 12 },
  input:   { borderWidth: 1, borderColor: '#ccc', padding: 8, borderRadius: 5, marginTop: 4 },

  radioRow: { flexDirection: 'row', justifyContent: 'space-around', marginVertical: 12 },

  map: { width: '100%', height: 220, marginTop: 10, borderRadius: 6 },
});

export default CreateRequestScreen;

